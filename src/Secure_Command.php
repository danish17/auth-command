<?php

/**
 * Adds HTTP auth to a site.
 *
 * ## EXAMPLES
 *
 *        # Add auth to a site
 *        $ ee secure example.com --auth
 *
 *        # Add auth without prompt
 *        $ ee secure example.com --auth [optional username] [optional password]
 *
 * @package ee-cli
 */

use EE\Utils;

class Secure_Command extends EE_Command {
	private $user;
	private $pass;
	private $site_name;
	private $db;

	/**
	 * Executes wp-cli command on a site.
	 *
	 * ## OPTIONS
	 *
	 * <site-name>
	 * : Name of website to be secured.
	 *
	 * [--auth]
	 * : Add http auth to entire site + admin tools.
	 *
	 * [<user>]
	 * : Username for http auth.
	 *
	 * [<pass>]
	 * : Password for http auth
	 */
	public function __invoke( $args, $assoc_args ) {

		$this->site_name = $args[0];
		if ( ! $this->db::site_in_db( $this->site_name ) ) {
			EE::error( "No site with name `$this->site_name` found." );
		}
		$global_reverse_proxy = 'ee-nginx-proxy';

		$auth = EE\Utils\get_flag_value( $assoc_args, 'auth' );

		if ( $auth ) {
			$this->user = $this->get_val( $args, 1, 'user name', 'easyengine' );
			$this->pass = $this->get_val( $args, 2, 'password', EE\Utils\random_password() );

			EE::launch( "docker exec $global_reverse_proxy htpasswd -bc /etc/nginx/htpasswd/$this->site_name $this->user $this->pass" );
			$this->reload();
		} else {
			EE::error( 'Only --auth is supported so far.' );
		}

	}

	private function get_val( $args, $arg_pos, $prompt, $default ) {
		if ( isset( $args[$arg_pos] ) ) {
			return $args[$arg_pos];
		} else {
			$val = EE::input( "Provide HTTP authentication $prompt [$default]:" );

			return empty( $val ) ? $default : $val;
		}
	}

	private function reload() {
		EE::launch( 'docker exec ee-nginx-proxy sh -c "/app/docker-entrypoint.sh /usr/local/bin/docker-gen /app/nginx.tmpl /etc/nginx/conf.d/default.conf; /usr/sbin/nginx -s reload"' );
	}
}
