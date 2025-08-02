<?php

namespace AndrewJDawes\WPPackageParser;

use AndrewJDawes\WPPackageParser\Parsers;
use ZipArchive;

/**
 * Class for interacting with WordPress packages (plugins and themes)
 */
class WPPackage
{
    /**
     * Metadata.
     *
     * @var array<string, string>
     */
    protected $metadata;

    /**
     * Package file path.
     *
     * @var string
     */
    private $package_path;

    /**
     * Package type.
     *
     * @var string
     */
    private $type = null;

    /**
     * Whether to parse the readme file.
     *
     * @var bool
     */
    private $parse_readme = true;

    /**
     * Construct a package instance and parse the provided zip file.
     *
     * @param $package_path
     */
    public function __construct(string $package_path, string|null $type = null, $parse_readme = true)
    {
        $this->package_path = $package_path;
        $this->type = $type;
        $this->parse_readme = $parse_readme;
        $this->metadata = [];
        $this->parse();
    }

    /**
     * Get slug.
     *
     * @return string|null
     */
    public function getSlug(): string|null
    {
        $metadata = $this->getMetaData();

        if (! isset($metadata['slug'])) {
            return null;
        }

        return $metadata['slug'];
    }

    /**
     * Get metadata.
     *
     * @return array<string, string>
     */
    public function getMetaData(): array
    {
        return $this->metadata;
    }

    private function detectTypeFromFileNameAndContent($file_name, $content): string|null
    {
        if ($file_name === 'style.css') {
            $headers = $this->parseHeadersFromContent($file_name, $content);
            if ($headers) {
                return 'theme';
            }
        }
        // if file ends with .php
        if (str_ends_with($file_name, '.php')) {
            $headers = $this->parseHeadersFromContent($file_name, $content);
            if ($headers) {
                return 'plugin';
            }
        }
        return null;
    }

    /**
     * Parse style.css file.
     *
     * @param string $file_name
     * @param string $content Contents of file
     *
     * @return null|array<string, string>
     */
    public function parseHeadersFromContent($file_name, $content): null|array
    {
        static $headers_by_file_name = [];
        if (array_key_exists($file_name, $headers_by_file_name)) {
            return $headers_by_file_name[$file_name];
        }
        $headers = null;
        if ($file_name === 'style.css') {
            $theme_parser  = new Parsers\ThemeParser();
            $headers = $theme_parser->parseStyle($content);
        } else if (str_ends_with($file_name, '.php')) {
            $plugin_parser = new Parsers\PluginParser();
            $headers = $plugin_parser->parsePlugin($content);
        } else if ($file_name === 'readme.txt') {
            $plugin_parser = new Parsers\PluginParser();
            $headers = $plugin_parser->parseReadme($content);
        }
        $headers_by_file_name[$file_name] = $headers;
        return $headers;
    }

    /**
     * Parse package.
     *
     * @return bool
     */
    private function parse(): bool
    {
        if (! $this->validateFile()) {
            return false;
        }

        $readme_metadata = [];

        $slug  = null;
        $zip   = $this->openPackage();
        $files = $zip->numFiles;

        for ($index = 0; $index < $files; $index++) {
            $info = $zip->statIndex($index);

            $file = $this->exploreFile($info['name']);
            if (! $file) {
                continue;
            }

            $slug      = $file['dirname'];
            $file_name = $file['name'] . '.' . $file['extension'];
            $content   = $zip->getFromIndex($index);

            if (is_null($this->type)) {
                $this->type = $this->detectTypeFromFileNameAndContent($file_name, $content);
            }

            if ($file_name === 'readme.txt' && $this->parse_readme) {
                $data = $this->parseHeadersFromContent($file_name, $content);
                if (null === $data) {
                    continue;
                }
                unset($data['name']);
                $data['readme'] = true;
                $readme_metadata = $data;
                continue;
            }

            if ($file['extension'] === 'php' && $this->type === 'plugin') {
                $headers = $this->parseHeadersFromContent($file_name, $content);
                if (null === $headers) {
                    continue;
                }
                //Add plugin file
                $plugin_file       = $slug . '/' . $file_name;
                $headers['plugin'] = $plugin_file;
                $this->metadata = array_merge($this->metadata, $headers);
                if (! $this->parse_readme) {
                    break;
                }
                continue;
            }

            if ($file_name === 'style.css' && $this->type === 'theme') {
                $headers = $this->parseHeadersFromContent($file_name, $content);
                if (null !== $headers) {
                    $this->metadata = array_merge($this->metadata, $headers);
                }
                if (! $this->parse_readme) {
                    break;
                }
                continue;
            }
        }

        if (empty($this->type)) {
            return false;
        }

        $this->metadata = array_merge($readme_metadata, $this->metadata);

        $this->metadata['slug'] = $slug;

        return true;
    }

    /**
     * Get package type.
     *
     * @return string|null
     */
    public function getType(): string|null
    {
        return $this->type;
    }

    /**
     * Explore file.
     *
     * @param string $file_name File name.
     *
     * @return bool|array<string, string>
     */
    private function exploreFile(string $file_name): bool|array
    {
        $data      = pathinfo($file_name);
        $dirname   = $data['dirname'];
        $depth     = substr_count($dirname, '/');
        $extension = ! empty($data['extension']) ? $data['extension'] : false;

        //Skip directories and everything that's more than 1 sub-directory deep.
        if ($depth > 0 || ! $extension) {
            return false;
        }

        return array(
            'dirname'   => $dirname,
            'name'      => $data['filename'],
            'extension' => $data['extension']
        );
    }

    /**
     * Validate package file.
     *
     * @return bool
     */
    private function validateFile()
    {
        $file = $this->package_path;

        if (! file_exists($file) || ! is_readable($file)) {
            return false;
        }

        if ('zip' !== pathinfo($file, PATHINFO_EXTENSION)) {
            return false;
        }

        return true;
    }

    /**
     * Open package file.
     *
     * @return false|ZipArchive
     */
    private function openPackage(): bool|ZipArchive
    {
        $file = $this->package_path;

        $zip = new ZipArchive();
        if ($zip->open($file) !== true) {
            return false;
        }

        return $zip;
    }
}
