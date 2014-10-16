<?php

class o2_Widget_Helper {
	private $array_version;
	private $has_widgets;
	private $first_widget_area_found;
	private $widget_areas;

	public function __construct() {
		$this->refresh();
	}

	function refresh() {
		$this->array_version = 0;
		$this->has_widgets = false;
		$this->first_widget_area_found = '';

		$this->widget_areas = get_option( 'sidebars_widgets' );

		$this->array_version = $this->widget_areas['array_version'];
		if ( 3 != $this->array_version ) {
			// fail silently on unsupported array versions
			return;
		}

		foreach ( (array) $this->widget_areas as $widget_area_key => $widget_area_value ) {
			// skip wp_inactive_widgets
			if ( 'wp_inactive_widgets' == $widget_area_key ) {
				continue;
			}
			// Skip orphaned widgets
			if ( 'orphaned_widgets' == substr( $widget_area_key, 0, 16 ) ) {
				continue;
			}
			// ignore non arrays (e.g. array_version)
			if ( ! is_array( $widget_area_value ) ) {
				continue;
			}
			// otherwise, save the first widget area found for later use
			if ( empty( $this->first_widget_area_found ) ) {
				$this->first_widget_area_found = $widget_area_key;
			}

			// make a note if ANY active widget area has at least one widget in it
			if ( 0 < count( $widget_area_value ) ) {
				$this->has_widgets = true;
				break;
			}
		}

		// Provide the default sidebar if none has been found
		if ( empty( $this->first_widget_area_found ) ) {
			$this->first_widget_area_found = 'sidebar-1';
		}
	}

	public function has_active_widgets() {
		if ( 3 != $this->array_version ) {
			// always return false on unsupported array versions
			return false;
		}

		return $this->has_widgets;
	}

	public function add_new_widget( $base, $multiwidget, $settings, $area = '' ) {
		if ( 3 != $this->array_version ) {
			// always fail on unsupported array versions
			return false;
		}

		// If no area was given, use the first area we found (besides the inactive area)
		if ( empty( $area ) ) {
			$area = $this->first_widget_area_found;
		}

		// Take the widget base and generate a unique id for it (e.g. base-1, base-2, etc)
		// that isn't already in the inactive widget area

		$instance_num = $this->get_first_unused_instance_num( $base );

		$widget_data = get_option( 'widget_' . $base );

		$widget_areas = $this->widget_areas;

		$widget_areas[$area][] = $base . "-" . $instance_num;
		$widget_settings = get_option( 'widget_' . $base );
		if ( ! is_array( $widget_settings ) ) {
			$widget_settings = array();
		}

		$widget_settings[$instance_num] = $settings;
		if ( $multiwidget ) {
			$widget_settings['_multiwidget'] = 1;
		}

		update_option( 'widget_' . $base, $widget_settings );
		update_option( 'sidebars_widgets', $widget_areas );

		// Update our view of the world
		$this->refresh();

		return true;
	}

	public function add_existing_widget( $widget_base_and_instance, $area = '' ) {
		if ( 3 != $this->array_version ) {
			// always fail on unsupported array versions
			return false;
		}

		// If no area was given, use the first area we found (besides the inactive area)
		if ( empty( $area ) ) {
			$area = $this->first_widget_area_found;
		}

		$widget_areas = $this->widget_areas;
		$widget_areas[$area][] = $widget_base_and_instance;
		update_option( 'sidebars_widgets', $widget_areas );

		// Update our view of the world
		$this->refresh();

		return true;
	}

	public function remove_widget( $widget_base_and_instance, $area = '' ) {
		if ( 3 != $this->array_version ) {
			return false;
		}

		if ( empty( $area ) ) {
			$area = $this->first_widget_area_found;
		}

		$widget_areas = $this->widget_areas;
		if ( ( $key = array_search( $widget_base_and_instance, $widget_areas[ $area ] ) ) !== false ) {
			unset( $widget_areas[ $area ][ $key ] );
		}
		update_option( 'sidebars_widgets', $widget_areas );

		// Update our view of the world
		$this->refresh();

		return true;
	}

	public function remove_widget_instances( $widget_base, $area = '' ) {
		if ( 3 != $this->array_version ) {
			return false;
		}

		if ( empty( $area ) ) {
			$area = $this->first_widget_area_found;
		}

		$widget_areas = $this->widget_areas;
		$widgets = $widget_areas[ $area ];
		$length = strlen( $widget_base );

		foreach ( $widgets as $widget ) {
			if ( $widget_base == substr( $widget, 0, $length ) ) {
				$this->remove_widget( $widget, $area );
			}
		}

		return true;
	}

	public function has_widget( $widget_base, $area = '', $args = array() ) {
		if ( 3 != $this->array_version ) {
			return false;
		}

		if ( empty( $area ) ) {
			$area = $this->first_widget_area_found;
		}

		$widget_areas = $this->widget_areas;
		$widgets = $this->widget_areas[ $area ];
		$has_widget = false;
		$length = strlen( $widget_base );

		// If checking widget attributes, get widget instances
		if ( ! empty( $args ) && is_array( $args ) ) {
			$instances = get_option( 'widget_' . $widget_base );
		}

		foreach ( $widgets as $widget ) {
			if ( $widget_base == substr( $widget, 0, $length ) ) {
				// Check widget attribute matches if we have them
				if ( ! empty( $args ) && is_array( $args ) ) {
					$widget_index = substr( $widget, $length + 1 );
					if ( array_key_exists( $widget_index, $instances ) ) {
						$matches = true;
						$instance = $instances[ $widget_index ];
						foreach ( $args as $key => $value ) {
							if ( $value !== $instance[ $key ] ) {
								$matches = false;
							}
							if ( $matches ) {
								$has_widget = true;
							}
						}
					}
				} else {
					$has_widget = true;
				}
			}
		}

		return $has_widget;
	}

	/*
	 * Give a widget's base name, return the first unused instance number
	 * for that widget
	 */
	private function get_first_unused_instance_num( $base ) {
		$instance_num = 0;
		do {
			$instance_num++; // start with 1, work our way up
			$found = false;
			$base_plus_instance = $base . "-" . $instance_num;
			foreach ( (array) $this->widget_areas as $widget_area_key => $widget_area_value ) {
				if ( ! is_array( $widget_area_value ) ) {
					continue;
				}
				if ( in_array( $base_plus_instance, $widget_area_value ) ) {
					$found = true;
				}
			}
		} while ( $found );

		return $instance_num;
	}
}
