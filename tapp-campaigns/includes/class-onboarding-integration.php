<?php
/**
 * TAPP Onboarding Plugin Integration
 * Provides soft integration with TAPP Onboarding plugin
 * Works with or without the onboarding plugin installed
 */

if (!defined('ABSPATH')) {
    exit;
}

class TAPP_Campaigns_Onboarding_Integration {

    private $is_active = false;

    public function __construct() {
        // Check if TAPP Onboarding plugin is active
        $this->is_active = class_exists('\TAPP\Onboarding\DB');
    }

    /**
     * Check if onboarding plugin is active
     */
    public function is_active() {
        return $this->is_active;
    }

    /**
     * Get user's department (works with or without onboarding)
     */
    public function get_user_department($user_id) {
        if ($this->is_active) {
            $dept_id = get_user_meta($user_id, 'tapp_department_id', true);
            if ($dept_id) {
                $dept = \TAPP\Onboarding\DB::get_department($dept_id);
                return $dept ? $dept->name : null;
            }
        }

        // Fallback: Check multiple meta keys
        $meta_keys = ['tapp_department', 'department', 'user_department'];
        foreach ($meta_keys as $key) {
            $value = get_user_meta($user_id, $key, true);
            if (!empty($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Get user's company
     */
    public function get_user_company($user_id) {
        if ($this->is_active) {
            $company_id = get_user_meta($user_id, 'tapp_company_id', true);
            if ($company_id) {
                $company = \TAPP\Onboarding\DB::get_company($company_id);
                return $company ? $company->name : null;
            }
        }

        return get_user_meta($user_id, 'company', true);
    }

    /**
     * Get user's role (Manager, CEO, Staff, Admin)
     */
    public function get_user_role($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return null;
        }

        if (in_array('administrator', $user->roles)) {
            return 'administrator';
        }
        if (in_array('ceo', $user->roles)) {
            return 'ceo';
        }
        if (in_array('manager', $user->roles)) {
            return 'manager';
        }
        if (in_array('staff', $user->roles)) {
            return 'staff';
        }

        return 'staff'; // Default
    }

    /**
     * Get all departments for dropdown
     */
    public function get_all_departments() {
        if ($this->is_active) {
            $depts = \TAPP\Onboarding\DB::departments();
            $result = [];
            foreach ($depts as $dept) {
                $result[] = [
                    'id' => $dept->id,
                    'name' => $dept->name,
                    'company' => $dept->company_name ?? ''
                ];
            }
            return $result;
        }

        // Fallback: Get unique departments from user meta
        global $wpdb;
        $departments = $wpdb->get_col("
            SELECT DISTINCT meta_value
            FROM {$wpdb->usermeta}
            WHERE meta_key IN ('tapp_department', 'department', 'user_department')
            AND meta_value != ''
            ORDER BY meta_value ASC
        ");

        return array_map(function($name) {
            return ['id' => $name, 'name' => $name, 'company' => ''];
        }, $departments);
    }

    /**
     * Get users by department
     */
    public function get_users_by_department($department) {
        if ($this->is_active && is_numeric($department)) {
            global $wpdb;
            $user_ids = $wpdb->get_col($wpdb->prepare("
                SELECT DISTINCT user_id
                FROM {$wpdb->usermeta}
                WHERE meta_key = 'tapp_department_id'
                AND meta_value = %d
            ", $department));

            if (empty($user_ids)) {
                return [];
            }

            return get_users(['include' => $user_ids]);
        }

        // Fallback: Search by department name
        return get_users([
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'tapp_department',
                    'value' => $department,
                    'compare' => '='
                ],
                [
                    'key' => 'department',
                    'value' => $department,
                    'compare' => '='
                ],
                [
                    'key' => 'user_department',
                    'value' => $department,
                    'compare' => '='
                ]
            ]
        ]);
    }

    /**
     * Check if user can create campaigns
     */
    public function can_create_campaigns($user_id) {
        $role = $this->get_user_role($user_id);
        return in_array($role, ['administrator', 'ceo', 'manager']);
    }

    /**
     * Check if user can view all campaigns
     */
    public function can_view_all_campaigns($user_id) {
        $role = $this->get_user_role($user_id);
        return in_array($role, ['administrator', 'ceo']);
    }

    /**
     * Check if user can edit campaign
     */
    public function can_edit_campaign($campaign_id, $user_id) {
        $role = $this->get_user_role($user_id);

        if (in_array($role, ['administrator', 'ceo'])) {
            return true;
        }

        if ($role === 'manager') {
            $campaign = TAPP_Campaigns_Campaign::get($campaign_id);
            return $campaign && $campaign->creator_id == $user_id;
        }

        return false;
    }
}

// Global helper function
function tapp_campaigns_onboarding() {
    static $integration = null;
    if ($integration === null) {
        $integration = new TAPP_Campaigns_Onboarding_Integration();
    }
    return $integration;
}
