<?php
/**
 * Elgg Jade Templating System
 *
 * @package ElggJade
 */

elgg_register_event_handler('init', 'system', function() {
	set_template_handler('jade_template_handler');
	_jade_register_classes();
});

function jade_template_handler($view, $vars) {
	
	if (!strstr($view, '.jade')) {
		return _jade_elgg_default_template_handler($view, $vars);
	}
	
	global $JADE;
	if (!isset($JADE) || !$JADE) {
		$JADE = new Jade\Jade(true);
	}

	$viewtype = elgg_get_viewtype();
	$view_location = elgg_get_view_location($view);
	$view_file = "$view_location$viewtype/$view.php";

	return $JADE->render($view_file);
	
}

function _jade_elgg_default_template_handler($view, $vars) {
	global $CONFIG;
	$viewtype = elgg_get_viewtype();
	$view_orig = $view;
	
	// Set up any extensions to the requested view
	if (isset($CONFIG->views->extensions[$view])) {
		$viewlist = $CONFIG->views->extensions[$view];
	} else {
		$viewlist = array(500 => $view);
	}

	// Start the output buffer, find the requested view file, and execute it
	ob_start();

	foreach ($viewlist as $priority => $view) {

		$view_location = elgg_get_view_location($view, $viewtype);
		$view_file = "$view_location$viewtype/$view.php";

		// try to include view
		if (!file_exists($view_file) || !include($view_file)) {
			// requested view does not exist
			$error = "$viewtype/$view view does not exist.";

			// attempt to load default view
			if ($viewtype !== 'default' && elgg_does_viewtype_fallback($viewtype)) {

				$default_location = elgg_get_view_location($view, 'default');
				$default_view_file = "{$default_location}default/$view.php";

				if (file_exists($default_view_file) && include($default_view_file)) {
					// default view found
					$error .= " Using default/$view instead.";
				} else {
					// no view found at all
					$error = "Neither $viewtype/$view nor default/$view view exists.";
				}
			}

			// log warning
			elgg_log($error, 'NOTICE');
		}
	}

	// Save the output buffer into the $content variable
	$content = ob_get_clean();

	// Plugin hook
	$params = array('view' => $view_orig, 'vars' => $vars, 'viewtype' => $viewtype);
	$content = elgg_trigger_plugin_hook('view', $view_orig, $params, $content);

	// backward compatibility with less granular hook will be gone in 2.0
	$content_tmp = elgg_trigger_plugin_hook('display', 'view', $params, $content);

	if ($content_tmp !== $content) {
		$content = $content_tmp;
		elgg_deprecated_notice('The display:view plugin hook is deprecated by view:view_name', 1.8);
	}

	return $content;

}

function _jade_register_classes() {
	spl_autoload_register(function($class) {
		if(!strstr($class, 'Jade')) {
			return;
		}
		$class = str_replace("\\", DIRECTORY_SEPARATOR, $class);
		require_once(elgg_get_plugins_path() . "jade/vendors/$class.php");
	});
}