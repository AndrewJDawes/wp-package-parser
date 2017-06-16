<?php

/**
 * Class Max_WP_Plugin
 *
 * @since 1.0.0
 */
class Max_WP_Plugin_Parser extends Max_WP_Package_Parser {
	/**
	 * Headers map.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	protected static $headersMap = array(
		'Name'        => 'Plugin Name',
		'PluginURI'   => 'Plugin URI',
		'Version'     => 'Version',
		'Description' => 'Description',
		'Author'      => 'Author',
		'AuthorURI'   => 'Author URI',
		'TextDomain'  => 'Text Domain',
		'DomainPath'  => 'Domain Path',
		'Network'     => 'Network',
	);

	/**
	 * Parse file readme.txt
	 *
	 * @since 1.0.0
	 *
	 * @param $content
	 *
	 * @return array
	 */
	public static function parser_readme( $content ) {
		$readmeTxtContents = trim( $content, " \t\n\r" );
		$readme            = array(
			'name'              => '',
			'contributors'      => array(),
			'donate'            => '',
			'tags'              => array(),
			'requires'          => '',
			'tested'            => '',
			'stable'            => '',
			'short_description' => '',
			'sections'          => array(),
		);

		//The readme.txt header has a fairly fixed structure, so we can parse it line-by-line
		$lines = explode( "\n", $readmeTxtContents );
		//Plugin name is at the very top, e.g. === My Plugin ===
		if ( preg_match( '@===\s*(.+?)\s*===@', array_shift( $lines ), $matches ) ) {
			$readme['name'] = $matches[1];
		} else {
			return null;
		}

		//Then there's a bunch of meta fields formatted as "Field: value"
		$headers   = array();
		$headerMap = array(
			'Contributors'      => 'contributors',
			'Donate link'       => 'donate',
			'Tags'              => 'tags',
			'Requires at least' => 'requires',
			'Tested up to'      => 'tested',
			'Stable tag'        => 'stable',
		);
		do { //Parse each readme.txt header
			$pieces = explode( ':', array_shift( $lines ), 2 );
			if ( array_key_exists( $pieces[0], $headerMap ) ) {
				if ( isset( $pieces[1] ) ) {
					$headers[ $headerMap[ $pieces[0] ] ] = trim( $pieces[1] );
				} else {
					$headers[ $headerMap[ $pieces[0] ] ] = '';
				}
			}
		} while ( trim( $pieces[0] ) != '' ); //Until an empty line is encountered

		//"Contributors" is a comma-separated list. Convert it to an array.
		if ( ! empty( $headers['contributors'] ) ) {
			$headers['contributors'] = array_map( 'trim', explode( ',', $headers['contributors'] ) );
		}

		//Likewise for "Tags"
		if ( ! empty( $headers['tags'] ) ) {
			$headers['tags'] = array_map( 'trim', explode( ',', $headers['tags'] ) );
		}

		$readme = array_merge( $readme, $headers );

		//After the headers comes the short description
		$readme['short_description'] = array_shift( $lines );

		//Finally, a valid readme.txt also contains one or more "sections" identified by "== Section Name =="
		$sections       = array();
		$contentBuffer  = array();
		$currentSection = '';
		foreach ( $lines as $line ) {
			//Is this a section header?
			if ( preg_match( '@^\s*==\s+(.+?)\s+==\s*$@m', $line, $matches ) ) {
				//Flush the content buffer for the previous section, if any
				if ( ! empty( $currentSection ) ) {
					$sectionContent              = trim( implode( "\n", $contentBuffer ) );
					$sections[ $currentSection ] = $sectionContent;
				}
				//Start reading a new section
				$currentSection = $matches[1];
				$contentBuffer  = array();
			} else {
				//Buffer all section content
				$contentBuffer[] = $line;
			}
		}
		//Flush the buffer for the last section
		if ( ! empty( $currentSection ) ) {
			$sections[ $currentSection ] = trim( implode( "\n", $contentBuffer ) );
		}

		//Apply Markdown to sections
		$sections = array_map( __CLASS__ . '::applyMarkdown', $sections );

		//This is only necessary if you intend to later json_encode() the sections.
		//json_encode() may encode certain strings as NULL if they're not in UTF-8.
		$sections = array_map( 'utf8_encode', $sections );

		$readme['sections'] = $sections;

		return $readme;
	}

	/**
	 * Transform Markdown markup to HTML.
	 *
	 * Tries (in vain) to emulate the transformation that WordPress.org applies to readme.txt files.
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	private static function applyMarkdown( $text ) {
		//The WP standard for readme files uses some custom markup, like "= H4 headers ="
		$text     = preg_replace( '@^\s*=\s*(.+?)\s*=\s*$@m', "<h4>$1</h4>\n", $text );
		$markdown = new Parsedown();

		return $markdown->parse( $text );
	}

	/**
	 * Parse the plugin contents to retrieve plugin's metadata headers.
	 *
	 * Adapted from the get_plugin_data() function used by WordPress.
	 * Returns an array that contains the following:
	 *        'Name' - Name of the plugin.
	 *        'Title' - Title of the plugin and the link to the plugin's web site.
	 *        'Description' - Description of what the plugin does and/or notes from the author.
	 *        'Author' - The author's name.
	 *        'AuthorURI' - The author's web site address.
	 *        'Version' - The plugin version number.
	 *        'PluginURI' - Plugin web site address.
	 *        'TextDomain' - Plugin's text domain for localization.
	 *        'DomainPath' - Plugin's relative directory path to .mo files.
	 *        'Network' - Boolean. Whether the plugin can only be activated network wide.
	 *
	 * If the input string doesn't appear to contain a valid plugin header, the function
	 * will return NULL.
	 *
	 * @param string $fileContents Contents of the plugin file
	 *
	 * @return array|null See above for description.
	 */
	public static function parsePluginFile( $fileContents ) {
		$headers = self::parseHeaders( $fileContents, self::$headersMap );

		$headers['Network'] = ( strtolower( $headers['Network'] ) === 'true' );

		//If it doesn't have a name, it's probably not a plugin.
		if ( empty( $headers['Name'] ) ) {
			return null;
		} else {
			return $headers;
		}
	}
}