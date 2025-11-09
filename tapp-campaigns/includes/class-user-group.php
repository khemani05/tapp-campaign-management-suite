<?php
/**
 * User Group Model Class
 * Handles user groups for quick participant addition
 */

if (!defined('ABSPATH')) {
    exit;
}

class TAPP_Campaigns_User_Group {

    /**
     * Create user group
     */
    public static function create($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_user_groups';

        $result = $wpdb->insert($table, [
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'creator_id' => get_current_user_id(),
            'department' => sanitize_text_field($data['department'] ?? ''),
        ]);

        if ($result) {
            $group_id = $wpdb->insert_id;

            // Add members if provided
            if (isset($data['user_ids']) && is_array($data['user_ids'])) {
                self::add_members($group_id, $data['user_ids']);
            }

            return $group_id;
        }

        return false;
    }

    /**
     * Get group by ID
     */
    public static function get($group_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_user_groups';

        $group = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $group_id
        ));

        if ($group) {
            // Get member count
            $group->member_count = self::count_members($group_id);
        }

        return $group;
    }

    /**
     * Get all groups
     */
    public static function get_all($args = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_user_groups';

        $where = ['1=1'];
        $params = [];

        // Filter by creator
        if (isset($args['creator_id'])) {
            $where[] = 'creator_id = %d';
            $params[] = $args['creator_id'];
        }

        // Filter by department
        if (isset($args['department'])) {
            $where[] = 'department = %s';
            $params[] = $args['department'];
        }

        // Search
        if (isset($args['search']) && !empty($args['search'])) {
            $where[] = '(name LIKE %s OR description LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
        }

        $where_sql = implode(' AND ', $where);
        $query = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY name ASC";

        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }

        $groups = $wpdb->get_results($query);

        // Add member counts
        foreach ($groups as $group) {
            $group->member_count = self::count_members($group->id);
        }

        return $groups;
    }

    /**
     * Update group
     */
    public static function update($group_id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_user_groups';

        // Check ownership
        $group = self::get($group_id);
        if (!$group || $group->creator_id != get_current_user_id()) {
            return false;
        }

        $update_data = [];

        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
        }

        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
        }

        if (isset($data['department'])) {
            $update_data['department'] = sanitize_text_field($data['department']);
        }

        if (empty($update_data)) {
            return false;
        }

        $result = $wpdb->update(
            $table,
            $update_data,
            ['id' => $group_id]
        );

        return $result !== false;
    }

    /**
     * Delete group
     */
    public static function delete($group_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_user_groups';
        $members_table = $wpdb->prefix . 'tapp_user_group_members';

        // Check ownership
        $group = self::get($group_id);
        if (!$group || $group->creator_id != get_current_user_id()) {
            return false;
        }

        // Delete members first
        $wpdb->delete($members_table, ['group_id' => $group_id]);

        // Delete group
        $result = $wpdb->delete($table, ['id' => $group_id]);

        return $result !== false;
    }

    /**
     * Add member to group
     */
    public static function add_member($group_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_user_group_members';

        // Check if already a member
        if (self::is_member($group_id, $user_id)) {
            return true;
        }

        $result = $wpdb->insert($table, [
            'group_id' => $group_id,
            'user_id' => $user_id,
        ]);

        return $result !== false;
    }

    /**
     * Add multiple members
     */
    public static function add_members($group_id, $user_ids) {
        $added = 0;
        foreach ($user_ids as $user_id) {
            if (self::add_member($group_id, $user_id)) {
                $added++;
            }
        }
        return $added;
    }

    /**
     * Remove member from group
     */
    public static function remove_member($group_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_user_group_members';

        $result = $wpdb->delete($table, [
            'group_id' => $group_id,
            'user_id' => $user_id,
        ]);

        return $result !== false;
    }

    /**
     * Check if user is member
     */
    public static function is_member($group_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_user_group_members';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE group_id = %d AND user_id = %d",
            $group_id,
            $user_id
        ));

        return $count > 0;
    }

    /**
     * Get group members
     */
    public static function get_members($group_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_user_group_members';

        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$table} WHERE group_id = %d ORDER BY added_at ASC",
            $group_id
        ));

        if (empty($user_ids)) {
            return [];
        }

        // Get user details
        $users = get_users([
            'include' => $user_ids,
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);

        // Add department info
        foreach ($users as $user) {
            $user->department = get_user_meta($user->ID, 'tapp_department', true);
            if (empty($user->department)) {
                $user->department = get_user_meta($user->ID, 'department', true);
            }
        }

        return $users;
    }

    /**
     * Get groups for user
     */
    public static function get_user_groups($user_id) {
        global $wpdb;
        $groups_table = $wpdb->prefix . 'tapp_user_groups';
        $members_table = $wpdb->prefix . 'tapp_user_group_members';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT g.* FROM {$groups_table} g
            INNER JOIN {$members_table} m ON g.id = m.group_id
            WHERE m.user_id = %d
            ORDER BY g.name ASC",
            $user_id
        ));
    }

    /**
     * Count members
     */
    public static function count_members($group_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_user_group_members';

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE group_id = %d",
            $group_id
        ));
    }

    /**
     * Count groups
     */
    public static function count($args = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_user_groups';

        $where = ['1=1'];
        $params = [];

        if (isset($args['creator_id'])) {
            $where[] = 'creator_id = %d';
            $params[] = $args['creator_id'];
        }

        if (isset($args['department'])) {
            $where[] = 'department = %s';
            $params[] = $args['department'];
        }

        $where_sql = implode(' AND ', $where);
        $query = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";

        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }

        return (int) $wpdb->get_var($query);
    }

    /**
     * Export group as CSV
     */
    public static function export_csv($group_id) {
        $group = self::get($group_id);
        if (!$group) {
            return false;
        }

        $members = self::get_members($group_id);

        $filename = sanitize_title($group->name) . '-members-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // Add BOM for Excel UTF-8 support
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Header
        fputcsv($output, ['User ID', 'Name', 'Email', 'Department']);

        // Data
        foreach ($members as $member) {
            fputcsv($output, [
                $member->ID,
                $member->display_name,
                $member->user_email,
                $member->department ?: '-'
            ]);
        }

        fclose($output);
        exit;
    }
}
