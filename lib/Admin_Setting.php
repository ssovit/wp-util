<?php

namespace Sovit\Utilities;

if (!class_exists(__NAMESPACE__."\Admin_Setting")) {
    class Admin_Setting {
        /**
         * @var mixed
         */
        public $page = null;

        /**
         * @var string
         */
        private $capability = "manage_options";

        /**
         * @var string
         */
        private $menu_icon = "";

        /**
         * @var mixed
         */
        private $menu_parent = false;

        /**
         * @var mixed
         */
        private $menu_position = null;

        /**
         * @var mixed
         */
        private $menu_title;

        /**
         * @var string
         */
        private $page_id = "sovit-settings";

        /**
         * @var string
         */
        private $page_title = "Settings";

        /**
         * @var array
         */
        private $sanitize_callbacks = array();

        /**
         * @var string
         */
        private $setting_key = "sovit_setting";

        /**
         * @var array
         */
        private $settings = array();

        /**
         * @var array
         */
        private $validate_callbacks = array();

        /**
         * @param $page_id
         * @param false $setting_key
         * @param false $page_title
         * @param false $menu_title
         * @return mixed
         */
        public function __construct($page_id = null, $setting_key = null, $page_title = null, $menu_title = null) {
            if (empty($page_id)) {
                $this->set_page_id($page_id);
            }
            if (empty($page_title)) {
                $this->set_page_title($page_title);
            }
            if (empty($menu_title)) {
                $this->set_menu_title($menu_title);
            }
            if (empty($setting_key)) {
                $this->set_setting_key($setting_key);
            }
            add_action('admin_menu', array($this, 'admin_menu'), 20);
            add_action('admin_init', array($this, 'register_settings_fields'));
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'), 10);
            return $this;
        }

        /**
         * @param $hook
         */
        public function admin_enqueue_scripts($hook) {
            if ($hook == $this->page) {
                wp_register_script('pickr', $this->get_url('assets/pickr.min.js'), array('jquery'), null, true);
                wp_enqueue_script($this->page_id . '-setting-js', $this->get_url('assets/settings.min.js'), array('jquery', 'pickr'), null, true);
                wp_register_style('pickr-css', $this->get_url('assets/pickr.css'), array());
                wp_enqueue_style($this->page_id . '-setting-css', $this->get_url('assets/settings.min.css'), array('pickr-css'));
            }
        }

        public function admin_menu() {
            if (empty($this->menu_parent)) {
                $this->page = add_menu_page(
                    $this->page_title,
                    $this->menu_title,
                    $this->capability,
                    $this->page_id,
                    array($this, 'display_settings_page'),
                    $this->menu_icon,
                    $this->menu_position
                );
            } else {
                $this->page = add_submenu_page(
                    $this->menu_parent,
                    $this->page_title,
                    $this->menu_title,
                    $this->capability,
                    $this->page_id,
                    array($this, 'display_settings_page'),
                    $this->menu_position
                );
            }
        }

        public function display_settings_page() {
            echo "<style>.settings-wrap .tab-wrapper {display: none;}.settings-wrap .tab-wrapper.tab-active {display: block;}</style>";
            echo '<div class="wrap settings-wrap ' . $this->page_id . '-wrap">';
            echo '<h1>' . $this->page_title . '</h1>';
            do_action("sovit/settings/" . $this->page_id . "/tabs/before");
            echo '<div id="settings-tabs-wrapper" class="nav-tab-wrapper">';

            foreach ($this->settings as $tab_id => $tab) {
                if (empty($tab['sections']) && !isset($tab['render_callback'])) {
                    continue;
                }

                $active_class = '';

                if ('general' === $tab_id) {
                    $active_class = ' nav-tab-active';
                }

                echo "<a id='tab-nav-" . esc_attr($tab_id) . "' class='nav-tab{$active_class}' href='#tab-" . esc_attr($tab_id) . "'>{$tab['label']}</a>";
            }
            echo '</div>';
            echo '<form id="' . esc_attr($this->page_id) . '-settings-form" method="post" action="options.php">';
            settings_fields($this->page_id);
            foreach ($this->settings as $tab_id => $tab) {
                if (empty($tab['sections']) && !isset($tab['render_callback'])) {
                    continue;
                }
                $active_class = '';

                if ('general' === $tab_id) {
                    $active_class = ' tab-active';
                }

                echo "<div id='tab-{$tab_id}' class='tab-wrapper" . esc_attr($active_class) . "'>";
                if (!empty($tab['render_callback']) && \is_callable($tab['render_callback'])) {
                    $tab['render_callback']();
                } else {
                    $first_section = true;
                    if (!empty($tab['sections'])) {
                        foreach ($tab['sections'] as $section_id => $section) {
                            $full_section_id = $this->page_id . '_' . $section_id . '_section';
                            if (false === $first_section) {
                                echo '<hr>';
                            }
                            $first_section = false;
                            if (!empty($section['label'])) {
                                echo "<h2>" . esc_html__($section['label']) . "</h2>";
                            }

                            if (!empty($section['render_callback']) && \is_callable($section['render_callback'])) {
                                $section['render_callback']();
                            } else {

                                echo '<table class="form-table">';

                                do_settings_fields($this->page_id, $full_section_id);

                                echo '</table>';
                            }
                        }
                    }
                    submit_button();
                }
                echo '</div>';
            }
            echo '</form>';
            echo '</div>';
            do_action("sovit/settings/" . $this->page_id . "/tabs/after");
        }

        /**
         * @return mixed
         */
        public function get_page() {
            return $this->page;
        }

        /**
         * @param $file
         */
        public function get_url($file = "") {
            return trailingslashit(plugin_dir_url(__FILE__)) . $file;
        }

        /**
         * @param $new
         * @param $old
         * @param $opt
         * @return mixed
         */
        public function on_options_update($new, $old, $opt) {
            $new_val = array();
            foreach ($new as $key => $val) {
                if (isset($this->sanitize_callbacks[$key]) and \is_callable($this->sanitize_callbacks[$key])) {
                    $new_val[$key] = $this->sanitize_callbacks[$key]($val);
                } else {
                    $new_val[$key] = $val;
                }
            }
            return $new_val;
        }

        public function register_settings_fields() {
            $this->build_settings();

            foreach ($this->settings as $tab_id => $tab) {
                if (!isset($tab['sections'])) {
                    continue;
                }

                foreach ($tab['sections'] as $section_id => $section) {
                    $full_section_id = $this->page_id . '_' . $section_id . '_section';

                    $label = isset($section['label']) ? $section['label'] : '';

                    $section_callback = isset($section['callback']) ? $section['callback'] : '__return_empty_string';

                    add_settings_section($full_section_id, $label, $section_callback, $this->page_id);

                    foreach ($section['fields'] as $field_id => $field) {

                        $this->register_setting_field($field_id, $field, $full_section_id, $this->setting_key, get_option($this->setting_key, array()));
                    }
                }
            }
            register_setting($this->page_id, $this->setting_key, array());
            add_filter("pre_update_option_" . $this->setting_key, array($this, "on_options_update"), 3, 10);
        }

        /**
         * @param $capability
         * @return mixed
         */
        public function set_capability($capability) {
            $this->capability = $capability;
            return $this;
        }

        /**
         * @param $icon
         * @return mixed
         */
        public function set_icon($icon) {
            $this->menu_icon = $icon;
            return $this;
        }

        /**
         * @param $parent
         * @return mixed
         */
        public function set_menu_parent($parent) {
            $this->menu_parent = $parent;
            return $this;
        }

        /**
         * @param $position
         * @return mixed
         */
        public function set_menu_position($position) {
            $this->menu_position = $position;
            return $this;
        }

        /**
         * @param $title
         * @return mixed
         */
        public function set_menu_title($title) {
            $this->menu_title = $title;
            return $this;
        }

        /**
         * @param $page_id
         * @return mixed
         */
        public function set_page_id($page_id) {
            $this->page_id = $page_id;
            return $this;
        }

        /**
         * @param $title
         * @return mixed
         */
        public function set_page_title($title) {
            $this->page_title = $title;
            return $this;
        }

        /**
         * @param $key
         * @return mixed
         */
        public function set_setting_key($key) {
            $this->setting_key = $key;
            return $this;
        }

        /**
         * @return mixed
         */
        private function build_settings() {
            $tabs = apply_filters('sovit/settings/' . $this->page_id . '/tabs', array(
                "general" => array(
                    "label" => esc_html__('General'),
                ),
            ));
            $sections = apply_filters('sovit/settings/' . $this->page_id . '/sections', array());
            $fields   = apply_filters('sovit/settings/' . $this->page_id . '/fields', array());

            $settings = array();
            foreach ($tabs as $tab_id => $tab) {
                $settings[$tab_id]             = $tab;
                $settings[$tab_id]["sections"] = array();
            }
            foreach ($sections as $section_id => $section) {
                if (!isset($section["tab"])) {
                    continue;
                }
                $tab_id = $section["tab"];
                if (isset($settings[$section["tab"]])) {
                    $settings[$section["tab"]]["sections"][$section_id] = $section;
                    $section_fields                                     = \array_filter($fields, function ($field) use ($section_id, $section) {
                        return $field["section"] === $section_id && $field["tab"] === $section["tab"];
                    });
                    $settings[$section["tab"]]["sections"][$section_id]["fields"] = $section_fields;
                }
            }
            $this->settings = $settings;
            return $this->settings;
        }

        /**
         * @param $field_id
         * @param $field
         * @param $section_id
         * @param $setting_key
         * @param array $value
         */
        private function register_setting_field($field_id, $field, $section_id, $setting_key = false, $value = array()) {
            if (false === $setting_key) {
                $setting_key = $this->setting_key;
            }
            $default = isset($args['default']) ? $args['default'] : "";
            $args    = array_merge($field, array(
                "name"    => $setting_key . '[' . $field_id . ']',
                "default" => $default,
                "value"   => isset($value[$field_id]) ? $value[$field_id] : $default,
                "id"      => sanitize_title($field['tab'] . " " . $field["section"] . " " . $field_id),
            ));

            $render_callback = array("\Sovit\Utilities\Controls", 'render');
            if (!empty($field['render_callback'])) {
                $render_callback = $field['render_callback'];
            }

            add_settings_field(
                $args['id'],
                isset($args['label']) ? $args['label'] : '',
                $render_callback,
                $this->page_id,
                $section_id,
                $args
            );
            if (isset($field["sanitize_callback"])) {
                $this->sanitize_callbacks[$field_id] = $field["sanitize_callback"];
            }
            if (isset($field["validate_callback"])) {
                $this->validate_callbacks[$field_id] = $field["validate_callback"];
            }
        }
    }
}
