<?php

/**
 * Class Max_WP_Theme
 *
 * @since 1.0.0
 */
class Max_WP_Theme_Parser extends Max_WP_Package_Parser {
	/**
	 * Header map.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	protected $headerMap = array(
		'Name'        => 'Theme Name',
		'ThemeURI'    => 'Theme URI',
		'Description' => 'Description',
		'Author'      => 'Author',
		'AuthorURI'   => 'Author URI',
		'Version'     => 'Version',
		'Template'    => 'Template',
		'Status'      => 'Status',
		'Tags'        => 'Tags',
		'TextDomain'  => 'Text Domain',
		'DomainPath'  => 'Domain Path',
	);

	/**
	 * Parse file style.css
	 *
	 * @param $fileContents
	 *
	 * @return null
	 */
	public function parse_style( $fileContents ) {
		$headers = $this->parseHeaders( $fileContents );

		$headers['Tags'] = array_filter( array_map( 'trim', explode( ',', strip_tags( $headers['Tags'] ) ) ) );

		//If it doesn't have a name, it's probably not a valid theme.
		if ( empty( $headers['Name'] ) ) {
			return null;
		} else {
			return $headers;
		}
	}
}