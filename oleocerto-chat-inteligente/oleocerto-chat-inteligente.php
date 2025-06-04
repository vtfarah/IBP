<?php
/*
Plugin Name: Oleocerto Chat Inteligente
Description: Chat flutuante integrado ao ChatGPT para buscar respostas com base no conteudo do oleocerto.com.
Version: 1.0.0
Author: Oleocerto
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Oleocerto_Chat_Inteligente {

    const OPTION_API_KEY = 'oleocerto_api_key';
    const OPTION_ENABLED = 'oleocerto_chat_enabled';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_footer', array( $this, 'print_chat_container' ) );
        add_action( 'wp_ajax_nopriv_oleocerto_chat', array( $this, 'handle_chat' ) );
        add_action( 'wp_ajax_oleocerto_chat', array( $this, 'handle_chat' ) );
    }

    public function register_settings_page() {
        add_options_page(
            'Oleocerto Chat Inteligente',
            'Oleocerto Chat',
            'manage_options',
            'oleocerto-chat',
            array( $this, 'settings_page_html' )
        );
    }

    public function register_settings() {
        register_setting( 'oleocerto_chat_options', self::OPTION_API_KEY );
        register_setting( 'oleocerto_chat_options', self::OPTION_ENABLED );
    }

    public function settings_page_html() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Oleocerto Chat Inteligente', 'oleocerto-chat' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'oleocerto_chat_options' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="<?php echo self::OPTION_API_KEY; ?>">API Key</label></th>
                        <td><input name="<?php echo self::OPTION_API_KEY; ?>" type="text" id="<?php echo self::OPTION_API_KEY; ?>" value="<?php echo esc_attr( get_option( self::OPTION_API_KEY ) ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo self::OPTION_ENABLED; ?>"><?php esc_html_e( 'Ativar Chat', 'oleocerto-chat' ); ?></label></th>
                        <td><input name="<?php echo self::OPTION_ENABLED; ?>" type="checkbox" id="<?php echo self::OPTION_ENABLED; ?>" value="1" <?php checked( 1, get_option( self::OPTION_ENABLED ), true ); ?> /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function enqueue_assets() {
        if ( ! get_option( self::OPTION_ENABLED ) ) {
            return;
        }
        wp_enqueue_style( 'oleocerto-chat-css', plugins_url( 'css/chat.css', __FILE__ ), array(), '1.0.0' );
        wp_enqueue_script( 'oleocerto-chat-js', plugins_url( 'js/chat.js', __FILE__ ), array('jquery'), '1.0.0', true );
        wp_localize_script( 'oleocerto-chat-js', 'oleocertoChat', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'oleocerto_chat_nonce' )
        ) );
    }

    public function print_chat_container() {
        if ( ! get_option( self::OPTION_ENABLED ) ) {
            return;
        }
        echo '<div id="oleocerto-chat-root"></div>';
    }

    public function handle_chat() {
        check_ajax_referer( 'oleocerto_chat_nonce', 'nonce' );

        $question = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
        if ( empty( $question ) ) {
            wp_send_json_error( 'Mensagem vazia.' );
        }

        $api_key = get_option( self::OPTION_API_KEY );
        if ( empty( $api_key ) ) {
            wp_send_json_error( 'API Key não configurada.' );
        }

        $response = $this->query_chatgpt( $question, $api_key );
        wp_send_json_success( $response );
    }

    private function query_chatgpt( $question, $api_key ) {
        $endpoint = 'https://api.openai.com/v1/chat/completions';
        $headers = array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        );
        $context = "Responda exclusivamente com base nas informações disponíveis no site oleocerto.com. Se não souber com certeza, diga que não há informações disponíveis no site sobre o assunto.";

        $messages = array(
            array( 'role' => 'system', 'content' => $context ),
            array( 'role' => 'user', 'content' => $question )
        );

        $body = json_encode( array(
            'model'    => 'gpt-3.5-turbo',
            'messages' => $messages,
            'temperature' => 0.2,
        ) );

        $request = wp_remote_post( $endpoint, array(
            'headers' => $headers,
            'body'    => $body,
            'timeout' => 20,
        ) );

        if ( is_wp_error( $request ) ) {
            return array( 'error' => $request->get_error_message() );
        }

        $body = wp_remote_retrieve_body( $request );
        $data = json_decode( $body, true );
        $answer = isset( $data['choices'][0]['message']['content'] ) ? $data['choices'][0]['message']['content'] : '';

        $related_posts = $this->get_related_posts( $question );

        return array(
            'answer' => $answer,
            'related' => $related_posts,
        );
    }

    private function get_related_posts( $query ) {
        $args = array(
            's' => $query,
            'post_type' => 'post',
            'posts_per_page' => 3,
        );
        $posts = get_posts( $args );
        $result = array();
        foreach ( $posts as $post ) {
            $result[] = array(
                'title' => get_the_title( $post->ID ),
                'link'  => get_permalink( $post->ID ),
                'image' => get_the_post_thumbnail_url( $post->ID, 'thumbnail' ),
                'excerpt' => wp_trim_words( $post->post_content, 20, '...' ),
            );
        }
        return $result;
    }
}

new Oleocerto_Chat_Inteligente();
