<?php
/**
 * Plugin Name: Comment Translator & Spam Checker
 * Description: Adds an inline AI assessment panel to the WordPress comment moderation screen. Translates comments and checks for spam using the Anthropic API.
 * Version: 1.0
 * Author: Dirk Tilger <dirk@systemication.com>
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the settings page under Settings → Comment Translator.
 */
function ct_register_settings_page() {
    add_options_page(
        'Comment Translator Settings',
        'Comment Translator',
        'manage_options',
        'comment-translator',
        'ct_render_settings_page'
    );
}
add_action( 'admin_menu', 'ct_register_settings_page' );

/**
 * Register the API key option.
 */
function ct_register_settings() {
    register_setting( 'ct_settings_group', 'comment_translator_api_key', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ) );
}
add_action( 'admin_init', 'ct_register_settings' );

/**
 * Render the settings page.
 */
function ct_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1>Comment Translator Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'ct_settings_group' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="comment_translator_api_key">Anthropic API Key</label></th>
                    <td>
                        <input type="password" id="comment_translator_api_key" name="comment_translator_api_key"
                               value="<?php echo esc_attr( get_option( 'comment_translator_api_key', '' ) ); ?>"
                               class="regular-text" autocomplete="off" />
                        <p class="description">Enter your Anthropic API key. It will be used to translate and assess comments.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/**
 * Enqueue inline JS and CSS on the edit-comments screen.
 */
function ct_enqueue_assets( $hook ) {
    if ( $hook !== 'edit-comments.php' ) {
        return;
    }

    $nonce = wp_create_nonce( 'ct_translate_nonce' );
    ?>
    <style>
        .ct-panel {
            padding: 8px 12px;
            margin: 4px 0 8px 0;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .ct-btn {
            display: inline-block;
            padding: 4px 12px;
            background: #2271b1;
            color: #fff;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            line-height: 1.6;
        }
        .ct-btn:hover { background: #135e96; }
        .ct-btn:disabled { opacity: 0.6; cursor: wait; }
        .ct-result { margin-top: 8px; font-size: 13px; line-height: 1.5; }
        .ct-badge {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            color: #fff;
            margin-right: 6px;
        }
        .ct-badge-lang { background: #2271b1; }
        .ct-verdict-spam { background: #d63638; }
        .ct-verdict-legit { background: #00a32a; }
        .ct-verdict-unsure { background: #dba617; }
        .ct-translation { margin: 6px 0; padding: 6px 10px; background: #fff; border-left: 3px solid #2271b1; }
        .ct-reason { margin-top: 4px; color: #555; font-style: italic; }
        .ct-error { color: #d63638; margin-top: 6px; }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var rows = document.querySelectorAll('#the-comment-list tr.comment');
        rows.forEach(function(row) {
            var commentId = row.id.replace('comment-', '');
            var commentCell = row.querySelector('.comment .column-comment');
            if (!commentCell) {
                // Try alternate selectors for different WP versions
                commentCell = row.querySelector('td.comment');
            }
            if (!commentCell) return;

            var panel = document.createElement('div');
            panel.className = 'ct-panel';
            panel.innerHTML = '<button type="button" class="ct-btn" data-comment-id="' + commentId + '">Translate &amp; Assess</button><div class="ct-result" id="ct-result-' + commentId + '"></div>';
            commentCell.appendChild(panel);
        });

        document.addEventListener('click', function(e) {
            if (!e.target.classList.contains('ct-btn')) return;
            var btn = e.target;
            var commentId = btn.getAttribute('data-comment-id');
            var resultDiv = document.getElementById('ct-result-' + commentId);
            btn.disabled = true;
            btn.textContent = 'Analyzing…';
            resultDiv.innerHTML = '';

            var formData = new FormData();
            formData.append('action', 'ct_translate_comment');
            formData.append('comment_id', commentId);
            formData.append('_ajax_nonce', '<?php echo esc_js( $nonce ); ?>');

            fetch(ajaxurl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(res) { return res.json(); })
            .then(function(resp) {
                btn.disabled = false;
                btn.textContent = 'Translate & Assess';
                if (!resp.success) {
                    resultDiv.innerHTML = '<div class="ct-error">' + escHtml(resp.data || 'Unknown error') + '</div>';
                    return;
                }
                var d = resp.data;
                var verdictClass = 'ct-verdict-' + (d.verdict || 'unsure');
                resultDiv.innerHTML =
                    '<span class="ct-badge ct-badge-lang">' + escHtml(d.language) + '</span>' +
                    '<span class="ct-badge ' + verdictClass + '">' + escHtml(d.verdict) + '</span>' +
                    '<div class="ct-translation">' + escHtml(d.translation) + '</div>' +
                    '<div class="ct-reason">' + escHtml(d.reason) + '</div>';
            })
            .catch(function(err) {
                btn.disabled = false;
                btn.textContent = 'Translate & Assess';
                resultDiv.innerHTML = '<div class="ct-error">Request failed: ' + escHtml(err.message) + '</div>';
            });
        });

        function escHtml(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str || ''));
            return div.innerHTML;
        }
    });
    </script>
    <?php
}
add_action( 'admin_footer-edit-comments.php', 'ct_enqueue_assets' );

/**
 * AJAX handler: translate and assess a comment via the Anthropic API.
 */
function ct_translate_comment_ajax() {
    check_ajax_referer( 'ct_translate_nonce' );

    if ( ! current_user_can( 'moderate_comments' ) ) {
        wp_send_json_error( 'Permission denied.', 403 );
    }

    $comment_id = isset( $_POST['comment_id'] ) ? absint( $_POST['comment_id'] ) : 0;
    if ( ! $comment_id ) {
        wp_send_json_error( 'Invalid comment ID.' );
    }

    $comment = get_comment( $comment_id );
    if ( ! $comment ) {
        wp_send_json_error( 'Comment not found.' );
    }

    $api_key = get_option( 'comment_translator_api_key', '' );
    if ( empty( $api_key ) ) {
        wp_send_json_error( 'Anthropic API key is not configured. Go to Settings → Comment Translator.' );
    }

    $comment_text = wp_strip_all_tags( $comment->comment_content );

    $prompt = 'Analyze the following user comment. Return ONLY valid JSON with these fields: '
        . '"language" (the detected language name in English), '
        . '"translation" (English translation of the comment; if already in English, repeat it), '
        . '"verdict" (one of "spam", "legit", or "unsure"), '
        . '"reason" (one sentence explaining the verdict). '
        . 'Do not include any text outside the JSON object.' . "\n\n"
        . 'Comment: ' . $comment_text;

    $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
        'timeout' => 30,
        'headers' => array(
            'Content-Type'      => 'application/json',
            'x-api-key'        => $api_key,
            'anthropic-version' => '2023-06-01',
        ),
        'body' => wp_json_encode( array(
            'model'      => 'claude-sonnet-4-20250514',
            'max_tokens' => 1000,
            'messages'   => array(
                array(
                    'role'    => 'user',
                    'content' => $prompt,
                ),
            ),
        ) ),
    ) );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( 'API request failed: ' . $response->get_error_message() );
    }

    $status_code = wp_remote_retrieve_response_code( $response );
    if ( $status_code !== 200 ) {
        $body = wp_remote_retrieve_body( $response );
        wp_send_json_error( 'API returned HTTP ' . $status_code . ': ' . $body );
    }

    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( empty( $data['content'][0]['text'] ) ) {
        wp_send_json_error( 'Unexpected API response format.' );
    }

    $ai_text = $data['content'][0]['text'];
    $result  = json_decode( $ai_text, true );

    if ( ! is_array( $result ) || ! isset( $result['language'], $result['translation'], $result['verdict'], $result['reason'] ) ) {
        wp_send_json_error( 'Could not parse AI response as valid JSON.' );
    }

    // Sanitize verdict to allowed values.
    $allowed_verdicts = array( 'spam', 'legit', 'unsure' );
    if ( ! in_array( $result['verdict'], $allowed_verdicts, true ) ) {
        $result['verdict'] = 'unsure';
    }

    wp_send_json_success( array(
        'language'    => sanitize_text_field( $result['language'] ),
        'translation' => sanitize_text_field( $result['translation'] ),
        'verdict'     => $result['verdict'],
        'reason'      => sanitize_text_field( $result['reason'] ),
    ) );
}
add_action( 'wp_ajax_ct_translate_comment', 'ct_translate_comment_ajax' );
