<?php
/**
 * Plugin Name: Codex Bridge for Elementor
 * Description: Read Elementor documents and update an existing element through authenticated REST requests.
 * Version: 1.0.0
 */

namespace WoodmartCodex\ElementorBridge;

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route('codex-elementor/v1', '/documents/(?P<id>[1-9]\d*)', [
        'methods' => 'GET, PATCH',
        'permission_callback' => __NAMESPACE__ . '\can_edit_document',
        'callback' => __NAMESPACE__ . '\handle_document',
    ]);
});

function can_edit_document(\WP_REST_Request $request) {
    $id = (int) $request['id'];
    return current_user_can('edit_post', $id)
        && ($request->get_method() === 'GET' || current_user_can('manage_options'));
}

function edit_element(array &$elements, string $id, array $settings): int {
    $matches = 0;
    foreach ($elements as &$element) {
        if (($element['id'] ?? '') === $id) {
            $element['settings'] = array_replace($element['settings'] ?? [], $settings);
            ++$matches;
        }

        if (!empty($element['elements'])) {
            $matches += edit_element($element['elements'], $id, $settings);
        }
    }
    return $matches;
}

function read_document($document): array {
    $elements = json_decode(get_post_meta($document->get_main_id(), '_elementor_data', true) ?: '[]', true);
    return [
        'id' => $document->get_main_id(),
        'version' => hash('sha256', wp_json_encode($elements)),
        'elements' => $elements,
    ];
}

function handle_document(\WP_REST_Request $request) {
    if (!did_action('elementor/loaded')) {
        return new \WP_Error('elementor_unavailable', 'Elementor is inactive.', ['status' => 503]);
    }

    $id = (int) $request['id'];
    $document = \Elementor\Plugin::$instance->documents->get($id);
    if (!$document || $document->get_main_id() != $id || !$document->is_built_with_elementor()) {
        return new \WP_Error('not_elementor_document', 'Open and save this document in Elementor first.', ['status' => 404]);
    }

    if ($request->get_method() === 'GET') {
        return rest_ensure_response(read_document($document));
    }

    $payload = $request->get_json_params();
    $element_id = $payload['element_id'] ?? null;
    $version = $payload['version'] ?? null;
    $settings = $payload['settings'] ?? null;
    if (!is_string($element_id) || !preg_match('/^[a-zA-Z0-9_-]+$/D', $element_id)
        || !is_string($version) || !is_array($settings) || !$settings
        || array_filter(array_keys($settings), 'is_int')) {
        return new \WP_Error('invalid_payload', 'Provide element_id, version and a settings object.', ['status' => 400]);
    }

    require_once ABSPATH . 'wp-admin/includes/post.php';
    if (wp_check_post_lock($id)) {
        return new \WP_Error('document_locked', 'Another user is editing this document.', ['status' => 409]);
    }

    // Serialize writes made through this endpoint for the same document.
    $lock = 'codex_elementor_write_' . $id;
    if (!add_option($lock, time(), '', false)) {
        return new \WP_Error('bridge_write_in_progress', 'A bridge write is already in progress.', ['status' => 409]);
    }

    try {
        $current = read_document($document);
        if (!is_array($current['elements'])) {
            return new \WP_Error('invalid_elementor_data', 'Stored Elementor data is invalid.', ['status' => 422]);
        }
        if (!hash_equals($current['version'], $version)) {
            return new \WP_Error('document_changed', 'Read the current document before retrying.', ['status' => 409]);
        }

        $elements = $current['elements'];
        if (edit_element($elements, $element_id, $settings) !== 1) {
            return new \WP_Error('element_not_unique', 'Expected exactly one element with this ID.', ['status' => 422]);
        }

        if ($elements !== $current['elements'] && !$document->save(['elements' => $elements])) {
            return new \WP_Error('elementor_save_failed', 'Elementor rejected the save.', ['status' => 500]);
        }

        return rest_ensure_response(read_document($document));
    } finally {
        delete_option($lock);
    }
}
