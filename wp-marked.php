<?php
/**
 * Plugin Name: Hello-Markdown
 * Description: Blogging with markdown, edit and preview in the real time! Hello-Markdown store raw markdown text in database!
 * Version: 1.0.0
 * Author: N2ET
 * Author URI: http://www.busyscript.com
 */

class HelloMarkdown {

    public static $domain = 'hello_markdown';

    public static $version = '1.0.0';

    public static $options = array(
        'post_types' => array()
    );

    public static $comment_type = 'comment';

    public function __construct() {
        register_activation_hook(__FILE__, array(__CLASS__, 'install'));
        register_uninstall_hook(__FILE__, array(__CLASS__, 'uninstall'));
        add_action('init', array($this, 'init'));
        add_action('admin_init', array($this, 'admin_init'));
    }

    public static function install() {
        update_option(self::key('version'), self::$version);
        add_option(self::key(), self::$options);
    }

    public static function uninstall() {
        delete_option(self::key('version'));
        delete_option(self::key());
    }

    public static function key($key = '') {
        if(!$key) {
            return self::$domain;
        }
        return self::$domain. '_' . $key;
    }

    // register static files, for admin and other page
    public static function register_static_res() {
        $plugin_dir = plugin_dir_url(__FILE__);

        wp_register_script(self::key('js_markdown_editor'), $plugin_dir . 'js/markdown_editor.js', array(), self::$version);
        wp_register_script(self::key('js_markdown_lib'), $plugin_dir . 'js/marked.js', array(), self::$version);

        wp_register_script(self::key('js_highlight'), $plugin_dir . 'js/highlight/highlight.pack.js', array(), self::$version);
        wp_register_style(self::key('css_highlight'), $plugin_dir . 'js/highlight/styles/default.css', array(), self::$version);

        wp_register_style(self::key('css_markdown_editor'), $plugin_dir . 'css/markdown_editor.css', array(), self::$version);
    }

    // enqueue all needed static files
    public function enqueue_static_res() {
        wp_enqueue_script(self::key('js_markdown_lib'), array('jquery'));
        wp_enqueue_script(self::key('js_markdown_editor'), array('jquery'));
        wp_enqueue_style(self::key('css_markdown_editor'));

        wp_enqueue_script(self::key('js_highlight'), array('jquery'));
        wp_enqueue_style(self::key('css_highlight'));
    }

    public function init() {
        add_filter('the_content', array($this, 'clear_post_content'), 1);
        add_filter('the_content', array($this, 'markdown_post_content'), 1000);
        add_filter('comment_text', array($this, 'clear_comment_content'), 1, 1);
        add_filter('comment_text', array($this, 'markdown_comment_content'), 1000, 2);
        add_action('wp_enqueue_scripts', array($this, 'add_static_res'));

        add_action('save_post', array($this, 'save_post'), 10, 3);
        add_filter('preprocess_comment', array($this, 'save_comment'));
    }

    public function admin_init() {
        $setting_group = 'writing';
        $section = self::key('_section');
        register_setting($setting_group, self::key(), array($this, 'validate'));
        add_settings_section($section, 'Hello Markdown', array($this, 'settings'), $setting_group);
        add_settings_field(self::key('posttypes'), __('Enable MarkDown for:', self::key()),
            array($this, 'settings_posttypes'), $setting_group, $section);

        add_filter( 'user_can_richedit', array($this,'can_richedit'), 99 );

        add_action('admin_enqueue_scripts', array($this,'admin_scripts'), 10, 1);
    }

    public function settings() {
        echo '<p>Select the post types or comments that will support Markdown.</p>';
    }

    public function settings_posttypes() {

        $options = get_option(self::key());
        $saved_types = (array) $options['post_types'];
        $types = get_post_types(array('public' => true), 'objects');
        unset($types['attachment']);

        $id = self::key('posttypes');
        $list = array();
        foreach($types as $type) {
            $checked = in_array($type->name, $saved_types);
            $list[] = $this->settings_posttypes_item($id, $type->name, $type->labels->name, $checked);
        }
        $list[] = $this->settings_posttypes_item($id, self::$comment_type, 'Comment', in_array(self::$comment_type, $saved_types));
        echo join('<br />', $list);
    }

    public function settings_posttypes_item($id, $value, $label, $checked) {
        $setting = self::key();
        $checked = checked($checked, true, false);
        return <<<END
<label>
    <input type="checkbox" id="{$id}" name="{$setting}[post_types][]" ${checked} value="{$value}" />{$label}
</label>
END;
    }

    public function validate($options) {
        return $options;
    }

    public function can_richedit($bool) {
        $screen = get_current_screen();
        $post_type = $screen->post_type;
        if($this->is_markdown_enable($post_type)) {
            return false;
        }

        return $bool;
    }

    /**
     * ；Determine whether markdown is enabled，only objects created by Hello_Markdown will be parsed
     * @param string $id_or_type  Type
     * @param string $id          ID
     * @return bool|mixed         Is markdown enabled
     */
    public function is_markdown_enable($id_or_type, $id = '') {
        if(is_int($id_or_type))
            $type = get_post_type($id_or_type);
        else
            $type = esc_attr($id_or_type);

        $options = get_option(self::key());
        $saved_types = (array) $options['post_types'];

        $post_type_markdown_enable = in_array($type, $saved_types);

        if(!$post_type_markdown_enable || empty($id)) {
            return $post_type_markdown_enable;
        }

        $post_type_markdown_enable = get_metadata($type, $id, '_' . self::key(), true);

        return $post_type_markdown_enable;
    }

    public function admin_scripts($hook){
        $screen = get_current_screen();
        $post_type = $screen->post_type;
        if(('post-new.php' == $hook || 'post.php' == $hook) && $this->is_markdown_enable($post_type)) {
            $this->add_static_res();
        }
    }

    public function add_static_res() {
        self::register_static_res();
        $this->enqueue_static_res();
    }

    public function clear_post_content($content) {
        $post_type = get_post_type();
        if($this->is_markdown_enable($post_type, get_the_ID())) {
            return 'content removed by markdown plugin';
        }

        return $content;
    }

    public function markdown_post_content($content) {
        $post = get_post();
        if($this->is_markdown_enable($post->post_type, get_the_ID())) {
            return '<div class="markdown-view post" style="display: none">' . $post->post_content . '</div>';
        }

        return $content;
    }

    public function clear_comment_content($content) {
        if($this->is_markdown_enable(self::$comment_type, get_comment_ID())) {
            return 'content removed by markdown plugin';
        }

        return $content;
    }

    public function markdown_comment_content($content, $comment) {
        if($this->is_markdown_enable(self::$comment_type, get_comment_ID())) {
            return '<div class="markdown-view comment" style="display: none">' . $comment->comment_content . '</div>';
        }

        return $content;
    }

    public function save_post($post_ID, $post, $update) {
        if($this->is_markdown_enable($post->post_type)) {
            $meta_key = '_' . self::key();
            $data = self::$version;
            if(!$update) {
                add_post_meta($post_ID, $meta_key, $data);
            }
        }
    }

    public function save_comment($comment) {
        if($this->is_markdown_enable(self::$comment_type)) {
            if(empty($comment['comment_meta'])) {
                $meta_key = '_' . self::key();
                $data = self::$version;
                $comment['comment_meta'] = array($meta_key => $data);
            }
        }
        return $comment;
    }

}

$hello_markdown = new HelloMarkdown();