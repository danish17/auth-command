<?php


namespace EE\Auth\Utils;

use EE;
use EE\Model\Auth;
use EE\Model\Option;
use function EE\Service\Utils\ensure_global_network_initialized;
use function EE\Utils\get_config_value;

/**
 * Initialize global admin tools auth if it's not present.
 *
 * @param string $display_log Wether to display log message or not.
 *
 * @throws \EE\ExitException
 * @throws \Exception
 */
function init_global_admin_tools_auth( $display_log = true ) {

	if ( ! empty( Auth::get_global_admin_tools_auth() ) || ! empty( Auth::get_global_auths() ) ) {
		if ( $display_log ) {
			EE::log( 'Global auth exists on admin-tools. Use `ee auth list global` to view credentials.' );
		}

		return;
	}

	verify_htpasswd_is_present();

	$pass      = \EE\Utils\random_password();
	$auth_data = array(
		'site_url' => 'default_admin_tools',
		'username' => 'easyengine',
		'password' => $pass,
	);

	Auth::create( $auth_data );

	EE::exec( sprintf( 'docker exec %s htpasswd -bc /etc/nginx/htpasswd/default_admin_tools %s %s', EE_PROXY_TYPE, $auth_data['username'], $auth_data['password'] ) );

	if ( $display_log ) {
		EE::success( sprintf( 'Global admin-tools auth added. Use `ee auth list global` to view credentials.' ) );
	}

	ensure_global_network_initialized();

	$frontend_subnet_ip = Option::get( 'frontend_subnet_ip' );
	EE::runcommand( "auth update global --ip='$frontend_subnet_ip'" );
}

/**
 * Check if htpasswd is present in the global-container.
 */
function verify_htpasswd_is_present() {

	EE\Service\Utils\nginx_proxy_check();
	EE::debug( 'Verifying htpasswd is present.' );
	if ( EE::exec( sprintf( 'docker exec %s sh -c \'command -v htpasswd\'', EE_PROXY_TYPE ) ) ) {
		return;
	}
	EE::error( sprintf( 'Could not find apache2-utils installed in %s.', EE_PROXY_TYPE ) );
}
