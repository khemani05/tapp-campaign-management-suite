# TAPP Campaigns Plugin - Complete Feature Documentation

**Version:** 1.0.0
**Last Updated:** 2025-11-09
**WordPress Version:** 6.4+
**PHP Version:** 7.4+
**WooCommerce Version:** 8.0+

---

## Table of Contents

1. [Overview](#overview)
2. [Database Schema](#database-schema)
3. [Core Features (Phases 1-9)](#core-features-phases-1-9)
4. [Optional Features](#optional-features)
5. [User Roles & Capabilities](#user-roles--capabilities)
6. [API Endpoints (AJAX)](#api-endpoints-ajax)
7. [Email System](#email-system)
8. [Cron Jobs](#cron-jobs)
9. [Configuration & Settings](#configuration--settings)
10. [File Structure](#file-structure)
11. [Third-Party Integrations](#third-party-integrations)

---

## Overview

TAPP Campaigns is an enterprise-scale WordPress plugin for managing time-boxed product selection campaigns for internal teams. It integrates deeply with WooCommerce and the TAPP Onboarding plugin to provide a comprehensive campaign management solution.

**Key Capabilities:**
- Create time-limited product selection campaigns
- Multiple campaign types (team, sales)
- WooCommerce product integration
- Participant management with user groups
- Real-time analytics and reporting
- Multiple page templates (Classic, Modern, Minimal, Hero)
- Homepage banner system
- Payment integration with automatic checkout
- Purchase order generation
- Activity logging for compliance
- Google Sheets export

---

## Database Schema

### 1. tapp_campaigns
**Purpose:** Main campaigns table

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED | Primary key |
| name | VARCHAR(255) | Campaign name |
| slug | VARCHAR(255) | URL-friendly slug (UNIQUE) |
| type | ENUM | 'team' or 'sales' |
| status | ENUM | 'draft', 'scheduled', 'active', 'ended', 'archived' |
| creator_id | BIGINT UNSIGNED | User ID of creator |
| department | VARCHAR(100) | Department (optional) |
| start_date | DATETIME | Campaign start time |
| end_date | DATETIME | Campaign end time |
| notes | TEXT | Internal notes |
| description | LONGTEXT | Public description (supports HTML) |
| selection_limit | INT | Max products per participant |
| edit_policy | ENUM | 'anytime', 'before_deadline', 'once' |
| require_approval | TINYINT(1) | Require manager approval |
| send_confirmation | TINYINT(1) | Send confirmation emails |
| send_reminder | TINYINT(1) | Enable reminder emails |
| reminder_days | INT | Days before deadline |
| ask_color | TINYINT(1) | Ask for color selection |
| ask_size | TINYINT(1) | Ask for size selection |
| product_ids | TEXT | Comma-separated product IDs |
| page_template | VARCHAR(50) | Template: classic, modern, minimal, hero |
| payment_enabled | TINYINT(1) | Enable WooCommerce checkout |
| generate_invoice | TINYINT(1) | Generate invoices |
| invoice_recipients | TEXT | Comma-separated emails |
| created_at | DATETIME | Creation timestamp |
| updated_at | DATETIME | Last update timestamp |

**Indexes:**
- `idx_status` on status
- `idx_creator` on creator_id
- `idx_dates` on start_date, end_date
- `idx_type` on type

---

### 2. tapp_campaign_participants
**Purpose:** Campaign participants (many-to-many junction)

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED | Primary key |
| campaign_id | BIGINT UNSIGNED | Campaign reference |
| user_id | BIGINT UNSIGNED | Participant user ID |
| added_by | BIGINT UNSIGNED | User who added participant |
| invited_at | DATETIME | Invitation timestamp |
| responded_at | DATETIME | Response timestamp (NULL if not responded) |
| reminder_sent_at | DATETIME | Last reminder timestamp |

**Indexes:**
- `unique_campaign_user` on (campaign_id, user_id) - UNIQUE
- `idx_campaign` on campaign_id
- `idx_user` on user_id
- `idx_responded` on responded_at

---

### 3. tapp_campaign_responses
**Purpose:** Individual product selections

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED | Primary key |
| campaign_id | BIGINT UNSIGNED | Campaign reference |
| user_id | BIGINT UNSIGNED | Participant user ID |
| product_id | BIGINT UNSIGNED | WooCommerce product ID |
| variation_id | BIGINT UNSIGNED | WooCommerce variation ID (0 if none) |
| color | VARCHAR(100) | Color selection (optional) |
| size | VARCHAR(50) | Size selection (optional) |
| quantity | INT | Quantity selected |
| submitted_at | DATETIME | Submission timestamp |

**Indexes:**
- `idx_campaign` on campaign_id
- `idx_user` on user_id
- `idx_product` on product_id
- `idx_submitted` on submitted_at

---

### 4. tapp_campaign_products
**Purpose:** Products associated with campaigns

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED | Primary key |
| campaign_id | BIGINT UNSIGNED | Campaign reference |
| product_id | BIGINT UNSIGNED | WooCommerce product ID |
| display_order | INT | Sort order (default 0) |
| is_featured | TINYINT(1) | Featured product flag |

**Indexes:**
- `unique_campaign_product` on (campaign_id, product_id) - UNIQUE
- `idx_campaign` on campaign_id
- `idx_product` on product_id

---

### 5. tapp_campaign_templates
**Purpose:** Reusable campaign templates

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED | Primary key |
| name | VARCHAR(255) | Template name |
| description | TEXT | Template description |
| type | ENUM | 'team', 'sales', 'custom' |
| creator_id | BIGINT UNSIGNED | User ID of creator |
| template_data | LONGTEXT | JSON-encoded campaign settings |
| product_ids | TEXT | Comma-separated product IDs |
| is_public | TINYINT(1) | Public/private flag |
| usage_count | INT | Number of times used |
| created_at | DATETIME | Creation timestamp |
| updated_at | DATETIME | Last update timestamp |

**Template Data Structure (JSON):**
```json
{
  "selection_limit": 3,
  "edit_policy": "anytime",
  "ask_color": true,
  "ask_size": true,
  "send_confirmation": true,
  "send_reminder": true,
  "reminder_days": 3,
  "page_template": "modern",
  "payment_enabled": false,
  "generate_invoice": false
}
```

**Indexes:**
- `idx_creator` on creator_id
- `idx_type` on type
- `idx_public` on is_public

---

### 6. tapp_user_groups
**Purpose:** User groups for quick participant selection

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED | Primary key |
| name | VARCHAR(255) | Group name |
| description | TEXT | Group description |
| creator_id | BIGINT UNSIGNED | User ID of creator |
| department | VARCHAR(100) | Department filter (optional) |
| created_at | DATETIME | Creation timestamp |
| updated_at | DATETIME | Last update timestamp |

**Indexes:**
- `idx_creator` on creator_id
- `idx_department` on department

---

### 7. tapp_user_group_members
**Purpose:** User group membership (many-to-many)

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED | Primary key |
| group_id | BIGINT UNSIGNED | Group reference |
| user_id | BIGINT UNSIGNED | Member user ID |
| added_at | DATETIME | Addition timestamp |

**Indexes:**
- `unique_group_user` on (group_id, user_id) - UNIQUE
- `idx_group` on group_id
- `idx_user` on user_id

---

### 8. tapp_activity_log
**Purpose:** Audit trail and activity logging

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED | Primary key |
| campaign_id | BIGINT UNSIGNED | Related campaign (NULL if not applicable) |
| user_id | BIGINT UNSIGNED | User who performed action |
| action | VARCHAR(100) | Action identifier (e.g., 'campaign_created') |
| action_type | ENUM | 'campaign', 'participant', 'response', 'template', 'group', 'system' |
| description | TEXT | Human-readable description |
| metadata | LONGTEXT | JSON-encoded additional data |
| ip_address | VARCHAR(45) | User's IP address |
| user_agent | VARCHAR(255) | Browser user agent |
| created_at | DATETIME | Action timestamp |

**Common Actions:**
- campaign_created, campaign_updated, campaign_deleted, campaign_status_changed
- participant_added, participant_removed
- response_submitted, response_updated, response_deleted
- reminder_sent
- template_created, template_used
- group_created
- csv_exported, google_sheets_exported

**Indexes:**
- `idx_campaign` on campaign_id
- `idx_user` on user_id
- `idx_action` on action
- `idx_action_type` on action_type
- `idx_created` on created_at

---

## Core Features (Phases 1-9)

### Phase 1: Core MVP
**Status:** ✅ Completed

**Features:**
- Database schema with 8 tables
- Campaign CRUD operations (Create, Read, Update, Delete)
- Participant management
- Response submission and tracking
- Email notifications system
- WooCommerce product integration
- Basic admin interface

**Key Files:**
- `includes/class-activator.php` - Database table creation
- `includes/class-campaign.php` - Campaign model and methods
- `includes/class-participant.php` - Participant management
- `includes/class-response.php` - Response handling
- `includes/class-email.php` - Email templates and sending

**Campaign Statuses:**
1. **draft** - Being created, not visible to participants
2. **scheduled** - Published but not yet started
3. **active** - Currently running
4. **ended** - Past end date
5. **archived** - Manually archived by manager

---

### Phase 2: Multiple Campaign Page Templates
**Status:** ✅ Completed

**Templates Available:**

#### 1. Classic Template
- Traditional card-based layout
- Product grid with images
- Checkbox selection
- Selection counter sidebar
- Countdown timer

**File:** `frontend/templates/layouts/campaign-classic.php`

#### 2. Modern Template
- Clean, minimalist design
- Large product images
- Hover effects
- Modern typography
- Mobile-optimized

**File:** `frontend/templates/layouts/campaign-modern.php`

#### 3. Minimal Template
- Ultra-clean design
- List-based layout
- Compact product cards
- Fast loading
- Text-focused

**File:** `frontend/templates/layouts/campaign-minimal.php`

#### 4. Hero Template
- Full-width hero banner
- Campaign description prominently displayed
- Large countdown timer
- Visual impact focus
- Best for important campaigns

**File:** `frontend/templates/layouts/campaign-hero.php`

**Template Selection:**
- Set during campaign creation
- Configurable in campaign settings
- Default: Classic template
- Preview available before publishing

---

### Phase 3: Homepage Banner System & Dashboard Enhancements
**Status:** ✅ Completed

**Homepage Banner Features:**

**Auto-Detection for 6+ Popular Themes:**
- Astra
- GeneratePress
- OceanWP
- Kadence
- Hello Elementor
- Twenty Twenty-Three
- Fallback for custom themes

**Banner Positions:**
- `before_header` - Above site header
- `after_header` - Below site header
- `before_content` - Above main content
- `before_footer` - Above footer
- `sticky_bottom` - Fixed to bottom of screen (mobile)

**Banner Content:**
- Active campaign count
- Calls-to-action
- Campaign urgency indicators
- Countdown for ending soon campaigns
- "View Campaigns" button

**Dismissal Options:**
- Session-based (until browser closes)
- 24 hours
- 7 days
- Permanent (until new campaign)

**File:** `frontend/class-banner.php`

**Dashboard Enhancements:**
- Campaign manager dashboard at `/campaign-manager/`
- Quick stats (total campaigns, active, responses)
- Recent campaigns table
- Quick action buttons
- Search and filtering

**File:** `frontend/templates/dashboard.php`

---

### Phase 4: Advanced Search & Bulk Actions
**Status:** ✅ Completed

**Search Functionality:**

**Product Search:**
- Real-time AJAX search
- Search by name, SKU, category
- Debounced input (300ms)
- Maximum 20 results
- Thumbnail preview

**User Search:**
- Search by name, email, department
- Role filtering
- Bulk selection
- Exclude already added participants

**AJAX Endpoints:**
- `tapp_search_products` - Product search
- `tapp_search_users` - User search

**Bulk Actions:**

**Participant Management:**
- Add multiple participants at once
- Remove multiple participants
- Send bulk reminder emails
- Import from user groups

**Response Management:**
- Delete multiple responses
- Export selected responses
- Bulk approval (if approval required)

**Files:**
- `includes/class-ajax.php` - Search handlers
- `assets/js/admin.js` - Frontend search UI

---

### Phase 5: Admin Settings
**Status:** ✅ Completed

**Settings Page:** `admin/views/settings.php`

**Configuration Options:**

**Banner Settings:**
- Enable/disable homepage banner
- Banner position selection
- Dismissal duration
- Mobile banner behavior

**Email Settings:**
- From name (default: site name)
- From email (default: admin email)
- Email templates customization
- Confirmation email toggle
- Reminder email toggle

**Campaign Defaults:**
- Default selection limit: 3
- Default edit policy: anytime
- Default template: classic
- Default reminder days: 3

**Display Settings:**
- Items per page: 20
- Date format
- Time zone

**Quick Select:**
- Enable/disable quick product selection
- Recent products count

**Access:** WordPress Admin → Campaigns → Settings

---

### Phase 6: Campaign Analytics, Reporting & CSV Export
**Status:** ✅ Completed

**Analytics Dashboard:**

**URL:** `/campaign-manager/{slug}/analytics`

**Key Metrics:**
1. **Participation Rate**
   - Total participants vs. responded
   - Percentage completion
   - Visual progress bar

2. **Product Popularity**
   - Most selected products
   - Selection frequency
   - Color/size breakdown

3. **Response Timeline**
   - Submissions over time
   - Chart.js line graph
   - Hourly/daily breakdown

4. **Department Analysis** (if available)
   - Responses by department
   - Chart.js pie chart
   - Comparative statistics

**Visualizations:**
- Chart.js 4.4.0 integration
- Line charts for timeline
- Pie charts for distribution
- Bar charts for product comparison
- Responsive design

**Files:**
- `frontend/templates/analytics.php` - Analytics page
- `assets/js/analytics.js` - Chart rendering
- `assets/css/analytics.css` - Chart styling

**CSV Export:**

**Three Export Types:**

1. **Audience Export**
   - All participants
   - Columns: Name, Email, Department, Role, Status, Invitation Date

2. **Responses Export**
   - All product selections
   - Columns: Participant, Email, Product, SKU, Color, Size, Quantity, Submitted Date

3. **Summary Export**
   - Aggregated data
   - Columns: Product, SKU, Total Quantity, Selection Count, Participants

**Export Features:**
- UTF-8 BOM for Excel compatibility
- Proper CSV escaping
- Large dataset support
- Direct download
- Activity logging

**Export Handler:** `admin/class-admin.php::handle_export()`

---

### Phase 7: Participant Management AJAX Handlers
**Status:** ✅ Completed

**AJAX Operations:**

**1. Load Response for Viewing**
```javascript
// Endpoint: tapp_load_response
// Purpose: View participant's submitted response
{
  campaign_id: 123,
  user_id: 456,
  mode: 'view' // or 'edit'
}
```

**2. Delete Response**
```javascript
// Endpoint: tapp_delete_response
// Purpose: Remove participant's response
{
  campaign_id: 123,
  user_id: 456
}
```

**3. Send Reminder**
```javascript
// Endpoint: tapp_send_reminder
// Purpose: Send reminder email to participant(s)
{
  campaign_id: 123,
  user_ids: [456, 789] // array of user IDs
}
```

**4. Remove Participant**
```javascript
// Endpoint: tapp_remove_participant
// Purpose: Remove user from campaign
{
  campaign_id: 123,
  user_id: 456
}
```

**Security:**
- Nonce verification (`tapp_analytics_nonce`)
- Permission checks (can_edit_campaign)
- User ownership validation
- SQL injection prevention

**Response Formats:**
```javascript
// Success
{
  success: true,
  data: {
    message: "Operation completed",
    // additional data
  }
}

// Error
{
  success: false,
  data: {
    message: "Error description"
  }
}
```

---

### Phase 8: Campaign Templates & User Groups (Database & Models)
**Status:** ✅ Completed

**Campaign Templates:**

**Purpose:** Save and reuse campaign configurations

**Template Contents:**
- All campaign settings (excluding dates/participants)
- Product associations
- Page template selection
- Email settings
- Selection policies

**Template Model Methods:**
```php
// Create template
TAPP_Campaigns_Template::create($name, $description, $campaign_data, $product_ids, $creator_id, $is_public)

// Get all templates
TAPP_Campaigns_Template::get_all($user_id)

// Get single template
TAPP_Campaigns_Template::get($template_id)

// Use template (create campaign from template)
TAPP_Campaigns_Template::use_template($template_id)

// Delete template
TAPP_Campaigns_Template::delete($template_id)

// Update usage count
TAPP_Campaigns_Template::increment_usage($template_id)
```

**File:** `includes/class-template.php`

**User Groups:**

**Purpose:** Quick participant selection

**Group Model Methods:**
```php
// Create group
TAPP_Campaigns_User_Group::create($name, $description, $creator_id, $department)

// Get all groups
TAPP_Campaigns_User_Group::get_all($creator_id)

// Get single group
TAPP_Campaigns_User_Group::get($group_id)

// Get members
TAPP_Campaigns_User_Group::get_members($group_id)

// Add member
TAPP_Campaigns_User_Group::add_member($group_id, $user_id)

// Remove member
TAPP_Campaigns_User_Group::remove_member($group_id, $user_id)

// Delete group
TAPP_Campaigns_User_Group::delete($group_id)
```

**File:** `includes/class-user-group.php`

---

### Phase 9: Templates & User Groups AJAX Handlers
**Status:** ✅ Completed

**Template AJAX Endpoints:**

**1. Create Template**
```javascript
// Endpoint: tapp_create_template
{
  name: "Q4 Team Campaign Template",
  description: "Standard template for quarterly team campaigns",
  campaign_data: {...}, // JSON object
  product_ids: "123,456,789",
  is_public: false
}
```

**2. Get Templates**
```javascript
// Endpoint: tapp_get_templates
// Returns: Array of user's templates
```

**3. Delete Template**
```javascript
// Endpoint: tapp_delete_template
{
  template_id: 123
}
```

**4. Use Template**
```javascript
// Endpoint: tapp_use_template
{
  template_id: 123
}
// Returns: Template data for form population
```

**User Group AJAX Endpoints:**

**1. Create Group**
```javascript
// Endpoint: tapp_create_group
{
  name: "Sales Team West Coast",
  description: "All sales representatives in western region",
  department: "Sales"
}
```

**2. Get Groups**
```javascript
// Endpoint: tapp_get_groups
// Returns: Array of user's groups with member counts
```

**3. Get Group Members**
```javascript
// Endpoint: tapp_get_group_members
{
  group_id: 123
}
// Returns: Array of group members with user details
```

**4. Delete Group**
```javascript
// Endpoint: tapp_delete_group
{
  group_id: 123
}
```

**5. Add Group Member**
```javascript
// Endpoint: tapp_add_group_member
{
  group_id: 123,
  user_id: 456
}
```

**6. Remove Group Member**
```javascript
// Endpoint: tapp_remove_group_member
{
  group_id: 123,
  user_id: 456
}
```

---

## Optional Features

### 1. Campaign Preview Mode (FSD Section 5.6)
**Status:** ✅ Completed
**Commit:** 8418ba1

**Purpose:** Allow campaign managers to preview campaigns before publishing

**Features:**

**Secure Preview URLs:**
```
/campaign/{slug}/?preview_mode=1&preview_token={secure_hash}
```

**Token Generation:**
```php
// Token: wp_hash('preview_' . campaign_id . '_' . creator_id)
// Ensures only campaign creator can preview
```

**Preview Mode Behavior:**
- Visual preview banner at top of page
- Form submissions disabled
- "Back to Dashboard" button
- Full campaign rendering (exactly as participants see it)
- No database writes

**Preview Banner:**
- Sticky positioning (stays visible while scrolling)
- Gradient purple background
- Eye icon indicator
- Clear messaging about disabled submissions
- Responsive design

**Security:**
- Token verification before loading preview
- Ownership validation (creator_id check)
- Constant flag: `TAPP_CAMPAIGN_PREVIEW_MODE`
- No preview access for non-creators

**Files Modified:**
- `includes/class-core.php` - Added preview_mode and preview_token query vars
- `includes/class-campaign.php` - Added get_preview_url() method
- `frontend/templates/campaign-page.php` - Preview banner HTML
- `frontend/templates/layouts/campaign-classic.php` - Disabled submit button

**Usage:**
```php
// Generate preview URL
$preview_url = TAPP_Campaigns_Campaign::get_preview_url($campaign_id);

// Check if in preview mode
if (defined('TAPP_CAMPAIGN_PREVIEW_MODE') && TAPP_CAMPAIGN_PREVIEW_MODE) {
    // Disable submissions
}
```

---

### 2. Payment/Checkout Integration (FSD Section 11)
**Status:** ✅ Completed
**Commit:** 8418ba1

**Purpose:** Enable WooCommerce checkout for payment-enabled campaigns

**Database Fields Used:**
- `payment_enabled` - Enable/disable payment
- `generate_invoice` - Auto-generate invoices
- `invoice_recipients` - Email addresses for invoices

**Payment Flow:**

**1. Response Submission:**
```
User submits selections
  ↓
Response saved to database
  ↓
If payment_enabled = true:
  ↓
Products added to WooCommerce cart
  ↓
Redirect to checkout page
```

**2. Cart Integration:**

**Cart Metadata:**
```php
[
  'tapp_campaign_id' => 123,
  'tapp_user_id' => 456,
  'tapp_is_campaign_item' => true,
  'tapp_color' => 'Blue',
  'tapp_size' => 'Large'
]
```

**Cart Protection:**
- Campaign items cannot be removed from cart
- Quantity cannot be changed
- Protected during entire checkout process

**3. Order Processing:**

**Hooks:**
- `woocommerce_order_status_completed` - Order marked complete
- `woocommerce_payment_complete` - Payment processed

**Order Metadata:**
```php
// Added to order line items
_tapp_campaign_id => 123
Campaign => "Q4 Team Campaign"
Color => "Blue"
Size => "Large"
```

**4. Invoice Generation:**

**Trigger:** Order completion for payment-enabled campaigns

**Invoice Email Contains:**
- Campaign name
- Order number and date
- Customer details
- Itemized product list
- Total amount
- Currency formatting

**Invoice Template:**
- Professional HTML design
- Responsive layout
- Company branding
- Print-friendly styling

**Files:**
- `includes/class-payment.php` - Full payment integration
- `includes/class-ajax.php` - Modified submit_response() for cart redirect
- `assets/js/frontend.js` - Redirect handling

**Key Methods:**
```php
// Add to cart
TAPP_Campaigns_Payment::add_to_cart($campaign_id, $user_id, $selections)

// Generate invoice
TAPP_Campaigns_Payment::generate_invoice($campaign_id, $order_id)

// Send invoice email
TAPP_Campaigns_Payment::send_invoice_email($recipients, $invoice_data)
```

**Configuration:**
1. Enable payment in campaign settings
2. Add invoice recipient emails (comma-separated)
3. WooCommerce must be active and configured
4. Products must be purchasable

---

### 3. Purchase Order Generation (FSD Section 12.6)
**Status:** ✅ Completed
**Commit:** 8acdca4

**Purpose:** Generate professional purchase orders for campaign responses

**Output Format:** HTML (browser print-to-PDF capable)

**Purchase Order Contents:**

**1. Header Section:**
- "Purchase Order" title
- Generation date/time
- Organization name

**2. Campaign Details Box:**
- Campaign name
- Campaign type
- Start and end dates
- Internal notes (if any)

**3. Order Summary Box:**
- Total participants
- Total items selected
- Total quantity
- Estimated total value (if prices available)

**4. Product Summary Table:**
| Product | SKU | Quantity | Unit Price | Total |
|---------|-----|----------|------------|-------|

**Features:**
- Aggregated by product
- Total quantities across all participants
- Price calculation (if products have prices)
- Sortable columns

**5. Detailed Breakdown by Participant:**
- Participant name and email
- Individual product selections
- Color and size (if applicable)
- Quantities per participant

**Styling:**

**Print Optimization:**
```css
@media print {
  body { padding: 0; }
  .print-button { display: none; }
  .page-break { page-break-before: always; }
}
```

**Features:**
- Professional typography
- Bordered tables
- Zebra-striped rows
- Page break controls
- Print button (hidden in print view)

**File Storage:**
- Location: `wp-content/uploads/tapp-campaigns/purchase-orders/`
- Filename: `po-{campaign_id}-{YYYY-MM-DD-HHiiss}.html`
- Permissions: Protected by WordPress

**Generation Methods:**
```php
// Manual generation
$filepath = TAPP_Campaigns_Purchase_Order::generate($campaign_id);

// Auto-generation on campaign end
TAPP_Campaigns_Purchase_Order::auto_generate_on_end($campaign_id);

// Send via email
TAPP_Campaigns_Purchase_Order::send_email($filepath, $campaign_id, $recipients);
```

**Email Delivery:**
- Attaches HTML file
- Professional email template
- Multiple recipients supported
- Uses invoice_recipients field

**Browser PDF Conversion:**
1. Open generated HTML file in browser
2. Click "Print / Save as PDF" button
3. Browser print dialog opens
4. Select "Save as PDF" destination
5. Professional PDF generated

**Files:**
- `includes/class-purchase-order.php` - PO generation class

---

### 4. Activity Logging System (FSD Section 12.5)
**Status:** ✅ Completed
**Commit:** 61ba58a

**Purpose:** Comprehensive audit trail for compliance and debugging

**Database Table:** `tapp_activity_log` (see Database Schema section)

**Logged Actions:**

**Campaign Actions:**
- `campaign_created` - New campaign created
- `campaign_updated` - Campaign settings modified
- `campaign_deleted` - Campaign removed
- `campaign_status_changed` - Status transition

**Participant Actions:**
- `participant_added` - User added to campaign
- `participant_removed` - User removed from campaign

**Response Actions:**
- `response_submitted` - Initial response submission
- `response_updated` - Response modified
- `response_deleted` - Response removed

**System Actions:**
- `reminder_sent` - Reminder emails sent
- `template_created` - Template saved
- `template_used` - Template applied to campaign
- `group_created` - User group created
- `csv_exported` - Data exported to CSV
- `google_sheets_exported` - Data exported to Google Sheets

**Logged Data:**

**For Each Activity:**
```php
[
  'campaign_id' => 123,           // Related campaign (if applicable)
  'user_id' => 456,               // User who performed action
  'action' => 'response_submitted',
  'action_type' => 'response',
  'description' => 'Response submitted with 3 product(s)',
  'metadata' => json_encode([     // Additional context
    'product_count' => 3,
    'product_ids' => [10, 20, 30]
  ]),
  'ip_address' => '192.168.1.1',
  'user_agent' => 'Mozilla/5.0...',
  'created_at' => '2025-11-09 14:30:00'
]
```

**Helper Methods:**
```php
// General logging
TAPP_Campaigns_Activity_Log::log($action, $action_type, $description, $campaign_id, $user_id, $metadata)

// Specific actions
TAPP_Campaigns_Activity_Log::log_campaign_created($campaign_id)
TAPP_Campaigns_Activity_Log::log_response_submitted($campaign_id, $user_id, $product_count)
TAPP_Campaigns_Activity_Log::log_participant_added($campaign_id, $participant_id)
// ... 15+ helper methods
```

**Admin UI:**

**Location:** WordPress Admin → Campaigns → Activity Log

**Features:**
- Paginated table (50 per page)
- Filter by action type
- Filter by campaign
- Filter by user
- Date/time display
- Color-coded badges by action type
- CSV export

**Color-Coded Badges:**
- 🟢 Campaign - Green
- 🔵 Participant - Blue
- 🟡 Response - Yellow
- ⚫ Template - Gray
- 🔵 Group - Light Blue
- 🔴 System - Red

**GDPR Compliance:**

**1. Auto-Cleanup:**
```php
// Delete logs older than 90 days
TAPP_Campaigns_Activity_Log::cleanup_old_logs(90);
```

**2. Right to be Forgotten:**
```php
// Delete all logs for a user
TAPP_Campaigns_Activity_Log::delete_user_logs($user_id);
```

**3. Campaign Data Deletion:**
```php
// Delete all logs for a campaign
TAPP_Campaigns_Activity_Log::delete_campaign_logs($campaign_id);
```

**CSV Export:**
- Full log export
- Filtered export
- Columns: ID, Date/Time, User, Campaign, Action, Type, Description, IP
- UTF-8 BOM for Excel
- Admin-only access

**Files:**
- `includes/class-activator.php` - Database table creation
- `includes/class-activity-log.php` - Logging class (600+ lines)
- `admin/views/activity-log.php` - Admin UI
- `admin/class-admin.php` - Menu integration

---

### 5. Google Sheets Export (FSD Section 9.2)
**Status:** ✅ Completed
**Commit:** b0ee8d7

**Purpose:** Export campaign responses to Google Sheets for advanced analysis

**Google API:** Google Sheets API v4

**Authentication:** OAuth2

**Setup Requirements:**

**1. Google Cloud Console:**
- Create project
- Enable Google Sheets API
- Create OAuth 2.0 credentials
- Configure redirect URI

**2. WordPress Settings:**
- Client ID
- Client Secret
- OAuth authorization (one-time)
- Access token (auto-refreshed)

**OAuth Flow:**

**1. Initial Authorization:**
```
Admin clicks "Connect to Google Sheets"
  ↓
Redirect to Google OAuth consent screen
  ↓
User grants permissions
  ↓
Redirect back with authorization code
  ↓
Exchange code for access token & refresh token
  ↓
Tokens saved to WordPress options
```

**2. Token Refresh (Automatic):**
```php
// Check expiration
if (token_expires_in < 5_minutes) {
  // Auto-refresh using refresh token
  TAPP_Campaigns_Google_Sheets::refresh_access_token();
}
```

**Export Data Format:**

**Spreadsheet Structure:**
```
Row 1 (Headers):
| Name | Email | Product ID | Product Name | Color | Size | Quantity | Submitted At |

Row 2+:
| John Doe | john@example.com | 123 | Blue T-Shirt | Blue | Large | 2 | 2025-11-09 14:30:00 |
```

**Dynamic Columns:**
- Color column only if `ask_color` = true
- Size column only if `ask_size` = true

**Export Methods:**

**1. Create New Spreadsheet:**
```php
$spreadsheet_id = TAPP_Campaigns_Google_Sheets::create_new_sheet($campaign_id, $access_token);
// Returns: Spreadsheet ID
// Creates: New Google Sheet with campaign name
```

**2. Export to Existing Spreadsheet:**
```php
$result = TAPP_Campaigns_Google_Sheets::export_to_sheet($campaign_id, $spreadsheet_id, $sheet_name);
// Appends data to specified sheet
```

**3. Auto-Sync (Optional):**
```php
// Enable auto-sync for campaign
update_post_meta($campaign_id, '_tapp_google_sheets_auto_sync', true);
update_post_meta($campaign_id, '_tapp_google_sheets_spreadsheet_id', $spreadsheet_id);

// Auto-syncs on each response submission
```

**AJAX Integration:**

**Endpoint:** `tapp_export_to_google_sheets`

**Request:**
```javascript
{
  campaign_id: 123,
  spreadsheet_id: "abc123xyz" // Optional, creates new if not provided
}
```

**Response:**
```javascript
{
  success: true,
  data: {
    message: "Successfully exported to Google Sheets",
    spreadsheet_id: "abc123xyz",
    spreadsheet_url: "https://docs.google.com/spreadsheets/d/abc123xyz/edit"
  }
}
```

**Key Methods:**
```php
// Check if configured
TAPP_Campaigns_Google_Sheets::is_configured()

// Get OAuth URL
TAPP_Campaigns_Google_Sheets::get_oauth_url()

// Exchange code for token
TAPP_Campaigns_Google_Sheets::exchange_code_for_token($code)

// Refresh token
TAPP_Campaigns_Google_Sheets::refresh_access_token()

// Ensure valid token
TAPP_Campaigns_Google_Sheets::ensure_valid_token()

// Export to sheet
TAPP_Campaigns_Google_Sheets::export_to_sheet($campaign_id, $spreadsheet_id)

// Create new sheet
TAPP_Campaigns_Google_Sheets::create_new_sheet($campaign_id, $access_token)

// Disconnect
TAPP_Campaigns_Google_Sheets::disconnect()

// Get spreadsheet URL
TAPP_Campaigns_Google_Sheets::get_spreadsheet_url($spreadsheet_id)

// Auto-sync
TAPP_Campaigns_Google_Sheets::auto_sync($campaign_id)
```

**Activity Logging:**
- All exports logged with spreadsheet ID
- Viewable in Activity Log
- Includes timestamp and user

**Error Handling:**
- API errors returned with descriptive messages
- Token expiration auto-handled
- Network error detection
- Rate limit awareness

**Security:**
- Tokens encrypted in WordPress options
- Permission checks before export
- Campaign ownership validation
- HTTPS required for OAuth

**Files:**
- `includes/class-google-sheets.php` - Full integration class (400+ lines)
- `includes/class-ajax.php` - Export AJAX handler

**Google Permissions Required:**
- `https://www.googleapis.com/auth/spreadsheets` (Read/write spreadsheets)

**Limitations:**
- Maximum 10 million cells per spreadsheet
- 2 million cells per sheet
- API quota: 300 requests per minute

---

## User Roles & Capabilities

### Custom Roles

**1. Manager**
- Can create campaigns
- Can edit own campaigns
- Can delete own campaigns
- Can view own campaign analytics
- Cannot edit others' campaigns

**Capabilities:**
```php
[
  'read' => true,
  'create_campaigns' => true,
  'edit_campaigns' => true,
  'delete_campaigns' => true,
  'view_campaigns' => true,
]
```

---

**2. CEO**
- Full campaign access
- Can edit all campaigns
- Can delete all campaigns
- Can view all analytics
- Can access activity logs

**Capabilities:**
```php
[
  'read' => true,
  'create_campaigns' => true,
  'edit_campaigns' => true,
  'edit_all_campaigns' => true,
  'delete_campaigns' => true,
  'delete_all_campaigns' => true,
  'view_all_campaigns' => true,
]
```

---

**3. Staff**
- Can only participate in campaigns
- Cannot create campaigns
- Cannot view others' responses
- Can view own submissions

**Capabilities:**
```php
[
  'read' => true,
  'participate_campaigns' => true,
]
```

---

**4. Administrator** (WordPress built-in)
- All CEO capabilities
- Plus: manage_campaign_settings

**Capabilities:**
```php
[
  // All CEO capabilities
  'manage_campaign_settings' => true,
]
```

---

**5. Customer** (WooCommerce built-in)
- Added `participate_campaigns` capability
- Can participate if invited

---

### Permission Checks

**Campaign Ownership:**
```php
// Check if user can edit specific campaign
function can_edit_campaign($campaign_id, $user_id) {
  $campaign = TAPP_Campaigns_Campaign::get($campaign_id);

  // CEO and Admin can edit all
  if (user_can($user_id, 'edit_all_campaigns')) {
    return true;
  }

  // Manager can edit own
  if ($campaign->creator_id == $user_id && user_can($user_id, 'edit_campaigns')) {
    return true;
  }

  return false;
}
```

**Participation Check:**
```php
// Check if user is participant
TAPP_Campaigns_Participant::is_participant($campaign_id, $user_id)
```

---

## API Endpoints (AJAX)

### Authentication
All AJAX endpoints require WordPress nonce verification.

**Admin Nonce:** `tapp_campaigns_admin`
**Frontend Nonce:** `tapp_campaigns_frontend`
**Analytics Nonce:** `tapp_analytics_nonce`

---

### Product & User Search

**1. Search Products**
```javascript
// Action: tapp_search_products
// Nonce: tapp_campaigns_admin
POST /wp-admin/admin-ajax.php
{
  action: 'tapp_search_products',
  nonce: 'xxx',
  search: 'blue shirt'
}

// Response
{
  success: true,
  data: [
    {
      id: 123,
      name: 'Blue T-Shirt',
      sku: 'BLUE-TSHIRT-001',
      price: '$19.99',
      image: 'https://...',
      type: 'simple'
    }
  ]
}
```

**2. Search Users**
```javascript
// Action: tapp_search_users
// Nonce: tapp_campaigns_admin
POST /wp-admin/admin-ajax.php
{
  action: 'tapp_search_users',
  nonce: 'xxx',
  search: 'john',
  exclude: [1, 2, 3] // Optional: exclude user IDs
}

// Response
{
  success: true,
  data: [
    {
      id: 456,
      name: 'John Doe',
      email: 'john@example.com',
      department: 'Sales',
      avatar: 'https://...'
    }
  ]
}
```

---

### Campaign Operations

**3. Submit Response**
```javascript
// Action: tapp_submit_response
// Nonce: tapp_campaigns_frontend
POST /wp-admin/admin-ajax.php
{
  action: 'tapp_submit_response',
  nonce: 'xxx',
  campaign_id: 123,
  selections: JSON.stringify([
    {
      product_id: 10,
      variation_id: 0,
      color: 'Blue',
      size: 'Large',
      quantity: 2
    }
  ])
}

// Response (non-payment campaign)
{
  success: true,
  data: {
    message: 'Your selections have been submitted successfully!',
    redirect: false
  }
}

// Response (payment-enabled campaign)
{
  success: true,
  data: {
    message: 'Redirecting to checkout...',
    redirect: 'https://example.com/checkout',
    cart_total: 39.98
  }
}
```

**4. Get Campaign Stats**
```javascript
// Action: tapp_get_stats
// Nonce: tapp_campaigns_admin
POST /wp-admin/admin-ajax.php
{
  action: 'tapp_get_stats',
  nonce: 'xxx',
  campaign_id: 123
}

// Response
{
  success: true,
  data: {
    total_participants: 50,
    total_responses: 42,
    participation_rate: 84,
    total_products_selected: 126,
    pending_participants: 8
  }
}
```

---

### Analytics Operations

**5. Load Response**
```javascript
// Action: tapp_load_response
// Nonce: tapp_analytics_nonce
POST /wp-admin/admin-ajax.php
{
  action: 'tapp_load_response',
  nonce: 'xxx',
  campaign_id: 123,
  user_id: 456,
  mode: 'view' // or 'edit'
}

// Response
{
  success: true,
  data: {
    user: {
      name: 'John Doe',
      email: 'john@example.com'
    },
    products: [
      {
        product_id: 10,
        name: 'Blue T-Shirt',
        color: 'Blue',
        size: 'Large',
        quantity: 2
      }
    ],
    submitted_at: '2025-11-09 14:30:00'
  }
}
```

**6. Delete Response**
```javascript
// Action: tapp_delete_response
// Nonce: tapp_analytics_nonce
POST /wp-admin/admin-ajax.php
{
  action: 'tapp_delete_response',
  nonce: 'xxx',
  campaign_id: 123,
  user_id: 456
}

// Response
{
  success: true,
  data: {
    message: 'Response deleted successfully'
  }
}
```

**7. Send Reminder**
```javascript
// Action: tapp_send_reminder
// Nonce: tapp_analytics_nonce
POST /wp-admin/admin-ajax.php
{
  action: 'tapp_send_reminder',
  nonce: 'xxx',
  campaign_id: 123,
  user_ids: [456, 789] // Array or 'all'
}

// Response
{
  success: true,
  data: {
    message: 'Reminder sent to 2 participant(s)',
    sent_count: 2
  }
}
```

**8. Remove Participant**
```javascript
// Action: tapp_remove_participant
// Nonce: tapp_analytics_nonce
POST /wp-admin/admin-ajax.php
{
  action: 'tapp_remove_participant',
  nonce: 'xxx',
  campaign_id: 123,
  user_id: 456
}

// Response
{
  success: true,
  data: {
    message: 'Participant removed successfully'
  }
}
```

---

### Template Operations

**9. Create Template**
```javascript
// Action: tapp_create_template
// Nonce: tapp_campaigns_admin
POST /wp-admin/admin-ajax.php
{
  action: 'tapp_create_template',
  nonce: 'xxx',
  name: 'Q4 Team Template',
  description: 'Standard quarterly team campaign',
  campaign_data: JSON.stringify({...}),
  product_ids: '10,20,30',
  is_public: false
}

// Response
{
  success: true,
  data: {
    message: 'Template created successfully',
    template_id: 5
  }
}
```

**10. Get Templates**
```javascript
// Action: tapp_get_templates
// Nonce: tapp_campaigns_admin
POST /wp-admin/admin-ajax.php
{
  action: 'tapp_get_templates',
  nonce: 'xxx'
}

// Response
{
  success: true,
  data: [
    {
      id: 5,
      name: 'Q4 Team Template',
      description: 'Standard quarterly team campaign',
      type: 'team',
      usage_count: 3,
      created_at: '2025-11-01 10:00:00'
    }
  ]
}
```

**11. Use Template**
```javascript
// Action: tapp_use_template
// Nonce: tapp_campaigns_admin
POST /wp-admin/admin-ajax.php
{
  action: 'tapp_use_template',
  nonce: 'xxx',
  template_id: 5
}

// Response
{
  success: true,
  data: {
    campaign_data: {...},
    product_ids: [10, 20, 30]
  }
}
```

**12. Delete Template**
```javascript
// Action: tapp_delete_template
// Nonce: tapp_campaigns_admin
POST /wp-admin/admin-ajax.php
{
  action: 'tapp_delete_template',
  nonce: 'xxx',
  template_id: 5
}

// Response
{
  success: true,
  data: {
    message: 'Template deleted successfully'
  }
}
```

---

### User Group Operations

**13. Create Group**
```javascript
// Action: tapp_create_group
// Nonce: tapp_campaigns_admin
POST /wp-admin/admin-ajax.php
{
  action: 'tapp_create_group',
  nonce: 'xxx',
  name: 'Sales Team West',
  description: 'Western region sales team',
  department: 'Sales'
}

// Response
{
  success: true,
  data: {
    message: 'Group created successfully',
    group_id: 7
  }
}
```

**14. Get Groups**
```javascript
// Action: tapp_get_groups
// Nonce: tapp_campaigns_admin
POST /wp-admin/admin-ajax.php
{
  action: 'tapp_get_groups',
  nonce: 'xxx'
}

// Response
{
  success: true,
  data: [
    {
      id: 7,
      name: 'Sales Team West',
      description: 'Western region sales team',
      member_count: 15,
      created_at: '2025-11-01 10:00:00'
    }
  ]
}
```

**15. Get Group Members**
```javascript
// Action: tapp_get_group_members
// Nonce: tapp_campaigns_admin
POST /wp-admin/admin-ajax.php
{
  action: 'tapp_get_group_members',
  nonce: 'xxx',
  group_id: 7
}

// Response
{
  success: true,
  data: [
    {
      id: 456,
      name: 'John Doe',
      email: 'john@example.com',
      department: 'Sales',
      added_at: '2025-11-01 10:30:00'
    }
  ]
}
```

**16. Add Group Member**
```javascript
// Action: tapp_add_group_member
// Nonce: tapp_campaigns_admin
POST /wp-admin/admin-ajax.php
{
  action: 'tapp_add_group_member',
  nonce: 'xxx',
  group_id: 7,
  user_id: 456
}

// Response
{
  success: true,
  data: {
    message: 'Member added successfully'
  }
}
```

**17. Remove Group Member**
```javascript
// Action: tapp_remove_group_member
// Nonce: tapp_campaigns_admin
POST /wp-admin/admin-ajax.php
{
  action: 'tapp_remove_group_member',
  nonce: 'xxx',
  group_id: 7,
  user_id: 456
}

// Response
{
  success: true,
  data: {
    message: 'Member removed successfully'
  }
}
```

**18. Delete Group**
```javascript
// Action: tapp_delete_group
// Nonce: tapp_campaigns_admin
POST /wp-admin/admin-ajax.php
{
  action: 'tapp_delete_group',
  nonce: 'xxx',
  group_id: 7
}

// Response
{
  success: true,
  data: {
    message: 'Group deleted successfully'
  }
}
```

---

### Export Operations

**19. Dismiss Banner**
```javascript
// Action: tapp_dismiss_banner
// Nonce: tapp_campaigns_frontend
POST /wp-admin/admin-ajax.php
{
  action: 'tapp_dismiss_banner',
  nonce: 'xxx',
  duration: '24hours' // or 'session', '7days', 'permanent'
}

// Response
{
  success: true,
  data: {
    message: 'Banner dismissed'
  }
}
```

**20. Export to Google Sheets**
```javascript
// Action: tapp_export_to_google_sheets
// Nonce: tapp_analytics_nonce
POST /wp-admin/admin-ajax.php
{
  action: 'tapp_export_to_google_sheets',
  nonce: 'xxx',
  campaign_id: 123,
  spreadsheet_id: 'abc123' // Optional
}

// Response
{
  success: true,
  data: {
    message: 'Successfully exported to Google Sheets',
    spreadsheet_id: 'abc123xyz',
    spreadsheet_url: 'https://docs.google.com/spreadsheets/d/abc123xyz/edit'
  }
}
```

---

## Email System

### Email Templates

**1. Campaign Invitation**
- **Trigger:** Participant added to campaign
- **Recipients:** Added participant
- **Subject:** `You're invited to participate in {campaign_name}`

**Variables:**
- `{campaign_name}` - Campaign name
- `{campaign_url}` - Direct link to campaign page
- `{campaign_description}` - Campaign description
- `{start_date}` - Campaign start date
- `{end_date}` - Campaign deadline
- `{selection_limit}` - Max products to select
- `{manager_name}` - Campaign creator name

**Template File:** `includes/class-email.php::send_invitation()`

---

**2. Response Confirmation**
- **Trigger:** Participant submits response (if `send_confirmation` enabled)
- **Recipients:** Participant who submitted
- **Subject:** `Your selections have been received - {campaign_name}`

**Variables:**
- `{campaign_name}` - Campaign name
- `{participant_name}` - Participant's name
- `{product_count}` - Number of products selected
- `{product_list}` - HTML list of selected products with quantities
- `{submission_date}` - Submission timestamp
- `{edit_url}` - Link to edit response (if policy allows)

**Template File:** `includes/class-email.php::send_confirmation()`

---

**3. Reminder Email**
- **Trigger:** Manual send or automated (if `send_reminder` enabled)
- **Recipients:** Participants who haven't responded
- **Subject:** `Reminder: {campaign_name} ends in {days} day(s)`

**Variables:**
- `{campaign_name}` - Campaign name
- `{campaign_url}` - Direct link to campaign page
- `{days_remaining}` - Days until deadline
- `{hours_remaining}` - Hours until deadline
- `{end_date}` - Campaign deadline
- `{selection_limit}` - Max products to select

**Template File:** `includes/class-email.php::send_reminder()`

---

**4. Campaign Ended Notification**
- **Trigger:** Campaign reaches end_date (cron job)
- **Recipients:** Campaign creator
- **Subject:** `Campaign Ended: {campaign_name} - {response_count} responses`

**Variables:**
- `{campaign_name}` - Campaign name
- `{total_participants}` - Total invited
- `{response_count}` - Total responses
- `{participation_rate}` - Percentage
- `{analytics_url}` - Link to analytics page
- `{end_date}` - Campaign end date

**Template File:** `includes/class-email.php::send_campaign_ended()`

---

**5. Invoice Email** (Payment-enabled campaigns)
- **Trigger:** Order completion
- **Recipients:** invoice_recipients (comma-separated)
- **Subject:** `[{site_name}] Campaign Invoice - Order #{order_number}`

**Content:**
- Order details table
- Customer information
- Product itemization
- Total amount with currency
- Professional HTML template

**Template:** Inline in `includes/class-payment.php::get_invoice_email_template()`

---

**6. Purchase Order Email**
- **Trigger:** Auto-generation on campaign end (if `generate_invoice` enabled)
- **Recipients:** invoice_recipients
- **Subject:** `[{site_name}] Purchase Order - {campaign_name}`

**Attachment:** HTML purchase order file

**Template:** Inline in `includes/class-purchase-order.php::send_email()`

---

### Email Configuration

**Settings Location:** WordPress Admin → Campaigns → Settings → Email

**Options:**
- **From Name:** Default: Site name
- **From Email:** Default: Admin email
- **Header Color:** Customizable
- **Logo:** Upload custom logo
- **Footer Text:** Customizable footer

**Email Styling:**
- Responsive HTML design
- Inline CSS for compatibility
- Gmail/Outlook tested
- Plain text fallback

---

## Cron Jobs

### Scheduled Events

**1. Campaign Status Updates**
- **Hook:** `tapp_campaigns_check_status`
- **Frequency:** Every 15 minutes
- **Purpose:** Update campaign statuses (scheduled → active, active → ended)

**Logic:**
```php
// Start scheduled campaigns
if (current_time > start_date && status == 'scheduled') {
  status = 'active'
}

// End active campaigns
if (current_time > end_date && status == 'active') {
  status = 'ended'
  trigger_campaign_ended_notifications()
  trigger_purchase_order_generation()
}
```

**File:** `includes/class-cron.php::check_campaign_status()`

---

**2. Send Reminder Emails**
- **Hook:** `tapp_campaigns_send_reminders`
- **Frequency:** Daily at 9:00 AM (site timezone)
- **Purpose:** Send reminder emails to participants who haven't responded

**Logic:**
```php
// For each active campaign with send_reminder = true
$days_until_end = (end_date - now) / 86400;

if ($days_until_end <= reminder_days && $days_until_end > 0) {
  // Get participants who haven't responded
  // Send reminder emails
  // Update reminder_sent_at timestamp
}
```

**File:** `includes/class-cron.php::send_reminders()`

---

**3. Cleanup Old Activity Logs**
- **Hook:** `tapp_campaigns_cleanup_logs`
- **Frequency:** Weekly (Sundays at 2:00 AM)
- **Purpose:** GDPR compliance - delete logs older than 90 days

**Logic:**
```php
TAPP_Campaigns_Activity_Log::cleanup_old_logs(90);
```

**File:** `includes/class-cron.php::cleanup_logs()`

---

**4. Refresh Google Sheets Tokens**
- **Hook:** `tapp_campaigns_refresh_google_token`
- **Frequency:** Daily at 3:00 AM
- **Purpose:** Ensure Google Sheets access tokens are fresh

**Logic:**
```php
if (token_expires_in < 7_days) {
  TAPP_Campaigns_Google_Sheets::refresh_access_token();
}
```

**File:** `includes/class-cron.php::refresh_google_token()`

---

### Cron Management

**Scheduling:** All cron jobs scheduled on plugin activation

**File:** `includes/class-cron.php`

**Hooks Setup:**
```php
public function schedule_events() {
  // Status check every 15 minutes
  if (!wp_next_scheduled('tapp_campaigns_check_status')) {
    wp_schedule_event(time(), 'every_15_minutes', 'tapp_campaigns_check_status');
  }

  // Reminders daily at 9 AM
  if (!wp_next_scheduled('tapp_campaigns_send_reminders')) {
    wp_schedule_event(strtotime('tomorrow 9:00'), 'daily', 'tapp_campaigns_send_reminders');
  }

  // Cleanup weekly
  if (!wp_next_scheduled('tapp_campaigns_cleanup_logs')) {
    wp_schedule_event(strtotime('next Sunday 2:00'), 'weekly', 'tapp_campaigns_cleanup_logs');
  }

  // Token refresh daily
  if (!wp_next_scheduled('tapp_campaigns_refresh_google_token')) {
    wp_schedule_event(strtotime('tomorrow 3:00'), 'daily', 'tapp_campaigns_refresh_google_token');
  }
}
```

**Deactivation Cleanup:**
```php
public function clear_scheduled_events() {
  wp_clear_scheduled_hook('tapp_campaigns_check_status');
  wp_clear_scheduled_hook('tapp_campaigns_send_reminders');
  wp_clear_scheduled_hook('tapp_campaigns_cleanup_logs');
  wp_clear_scheduled_hook('tapp_campaigns_refresh_google_token');
}
```

---

## Configuration & Settings

### Plugin Settings Page

**Location:** WordPress Admin → Campaigns → Settings

**Tabs:**

#### 1. General Settings
- **Items per page:** 10, 20, 50, 100
- **Default campaign template:** classic, modern, minimal, hero
- **Default selection limit:** 1-50
- **Enable quick select:** Yes/No

#### 2. Banner Settings
- **Enable homepage banner:** Yes/No
- **Banner position:**
  - Before header
  - After header
  - Before content
  - Before footer
  - Sticky bottom (mobile)
- **Dismissal duration:**
  - Session
  - 24 hours
  - 7 days
  - Permanent
- **Show on mobile:** Yes/No

#### 3. Email Settings
- **From name:** Text input
- **From email:** Email input
- **Header color:** Color picker
- **Logo:** Media uploader
- **Footer text:** Textarea
- **Enable confirmation emails:** Yes/No
- **Enable reminder emails:** Yes/No
- **Default reminder days:** 1-14

#### 4. Google Sheets Integration
- **Client ID:** Text input
- **Client Secret:** Password input
- **Authorization status:** Connected/Not Connected
- **Connect button:** Triggers OAuth flow
- **Disconnect button:** Removes tokens
- **Test connection:** Validates credentials

**Settings Storage:**
- WordPress options table
- Option names: `tapp_campaigns_{setting_name}`

**File:** `admin/views/settings.php`

---

### Campaign-Level Settings

**Available during campaign creation/editing:**

**Basic Settings:**
- Campaign name (required)
- Campaign type (team/sales)
- Department (optional)
- Description (WYSIWYG editor)
- Internal notes (private)

**Timing:**
- Start date & time
- End date & time
- Time zone awareness

**Selection Rules:**
- Selection limit (1-50)
- Edit policy (anytime, before_deadline, once)
- Require manager approval (yes/no)

**Product Configuration:**
- Product search & selection
- Product display order
- Featured products
- Ask for color (yes/no)
- Ask for size (yes/no)

**Participant Management:**
- Individual user selection
- User group selection
- Bulk import

**Communication:**
- Send confirmation emails (yes/no)
- Send reminder emails (yes/no)
- Reminder days before deadline

**Payment & Invoicing:**
- Enable payment (yes/no)
- Generate invoice (yes/no)
- Invoice recipient emails (comma-separated)

**Template:**
- Page template selection
- Preview before publish

---

## File Structure

```
tapp-campaigns/
│
├── tapp-campaigns.php              # Main plugin file
│
├── includes/                       # PHP Classes
│   ├── class-activator.php         # Plugin activation (database creation)
│   ├── class-deactivator.php       # Plugin deactivation (cleanup)
│   ├── class-core.php              # Core initialization
│   ├── class-database.php          # Database query helpers
│   ├── class-campaign.php          # Campaign model & methods
│   ├── class-participant.php       # Participant management
│   ├── class-response.php          # Response handling
│   ├── class-email.php             # Email system
│   ├── class-ajax.php              # AJAX handlers (800+ lines)
│   ├── class-cron.php              # Cron jobs
│   ├── class-template.php          # Campaign templates
│   ├── class-user-group.php        # User groups
│   ├── class-templates.php         # Template helpers
│   ├── class-payment.php           # WooCommerce payment integration
│   ├── class-purchase-order.php    # Purchase order generation
│   ├── class-activity-log.php      # Activity logging (600+ lines)
│   ├── class-google-sheets.php     # Google Sheets API (400+ lines)
│   └── class-onboarding-integration.php  # TAPP Onboarding integration
│
├── admin/                          # Admin Interface
│   ├── class-admin.php             # Admin menu & pages
│   ├── class-dashboard.php         # Admin dashboard
│   └── views/                      # Admin templates
│       ├── campaigns-list.php      # Campaign list table
│       ├── settings.php            # Settings page
│       └── activity-log.php        # Activity log page
│
├── frontend/                       # Frontend Components
│   ├── class-frontend.php          # Frontend initialization
│   ├── class-navigation.php        # Navigation menu
│   ├── class-campaign-page.php     # Campaign page handler
│   ├── class-banner.php            # Homepage banner system
│   │
│   └── templates/                  # Frontend templates
│       ├── dashboard.php           # Campaign manager dashboard
│       ├── campaign-page.php       # Main campaign page
│       ├── analytics.php           # Analytics page (400+ lines)
│       │
│       └── layouts/                # Campaign page layouts
│           ├── campaign-classic.php   # Classic template
│           ├── campaign-modern.php    # Modern template
│           ├── campaign-minimal.php   # Minimal template
│           └── campaign-hero.php      # Hero template
│
├── assets/                         # Static Assets
│   ├── css/
│   │   ├── admin.css               # Admin styles
│   │   ├── frontend.css            # Frontend styles
│   │   └── analytics.css           # Analytics styles (550+ lines)
│   │
│   └── js/
│       ├── admin.js                # Admin JavaScript
│       ├── frontend.js             # Frontend JavaScript
│       └── analytics.js            # Analytics & Chart.js (220+ lines)
│
└── languages/                      # Translation files
    └── tapp-campaigns.pot          # Translation template
```

**Total Lines of Code:** ~15,000+

**Key Metrics:**
- PHP Classes: 19
- Database Tables: 8
- AJAX Endpoints: 20
- Email Templates: 6
- Cron Jobs: 4
- Page Templates: 4
- Admin Pages: 3

---

## Third-Party Integrations

### 1. WooCommerce
**Version Required:** 8.0+

**Integration Points:**

**Products:**
- Product search and selection
- Product display in campaigns
- Variation support
- Price display
- Image thumbnails

**Cart & Checkout:**
- Add campaign items to cart
- Cart metadata tracking
- Protected cart items
- Automatic checkout redirect

**Orders:**
- Order completion hooks
- Order metadata
- Invoice generation
- Payment tracking

**Methods Used:**
```php
wc_get_product($product_id)
wc_get_product_categories()
wc_price($amount)
wc_get_checkout_url()
WC()->cart->add_to_cart()
WC()->cart->get_cart()
```

---

### 2. TAPP Onboarding Plugin
**Purpose:** User department and role management

**Integration Points:**

**User Data:**
- Department assignment
- Role hierarchy
- Permission inheritance

**Methods Used:**
```php
tapp_campaigns_onboarding()->get_user_department($user_id)
tapp_campaigns_onboarding()->can_create_campaigns($user_id)
tapp_campaigns_onboarding()->can_edit_campaign($campaign_id, $user_id)
```

**Fallback:** If TAPP Onboarding not installed, uses WordPress built-in roles

---

### 3. Google Sheets API v4
**Purpose:** Export campaign data to Google Sheets

**Requirements:**
- Google Cloud Console project
- OAuth 2.0 credentials
- Google Sheets API enabled

**OAuth Scopes:**
- `https://www.googleapis.com/auth/spreadsheets`

**API Endpoints Used:**
- `POST https://sheets.googleapis.com/v4/spreadsheets` - Create spreadsheet
- `POST https://sheets.googleapis.com/v4/spreadsheets/{id}/values/{range}:append` - Append data
- `POST https://oauth2.googleapis.com/token` - Token exchange/refresh

**Rate Limits:**
- 300 requests per minute per project
- 100 requests per second per user

---

### 4. Chart.js
**Version:** 4.4.0
**CDN:** `https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js`

**Purpose:** Analytics data visualization

**Chart Types Used:**
- Line charts (response timeline)
- Pie charts (department distribution)
- Bar charts (product popularity)

**Customization:**
- Custom color schemes
- Responsive sizing
- Tooltip formatting
- Legend positioning

**File:** `assets/js/analytics.js`

---

### 5. WordPress Core Features

**Custom Post Types:** Not used (uses custom tables for performance)

**Rewrite Rules:**
```php
// Campaign pages
add_rewrite_rule('^campaign/([^/]+)/?$', 'index.php?campaign=$matches[1]')

// Campaign manager
add_rewrite_rule('^campaign-manager/?$', 'index.php?campaign_manager=1')

// Analytics
add_rewrite_rule('^campaign-manager/([^/]+)/analytics/?$', 'index.php?campaign=$matches[1]&campaign_action=analytics')
```

**Query Vars:**
```php
'campaign'
'campaign_manager'
'campaign_action'
'preview_mode'
'preview_token'
```

**Capabilities:** See "User Roles & Capabilities" section

**Options API:**
- All settings stored in `wp_options`
- Autoloaded for performance
- Prefixed with `tapp_campaigns_`

**Transients API:**
- Banner dismissal tracking
- Temporary data caching

---

## Summary

**TAPP Campaigns Plugin v1.0.0** is a comprehensive, enterprise-grade solution for managing internal product selection campaigns with:

✅ **9 Core Development Phases** - Fully implemented
✅ **5 Optional Features** - All completed
✅ **8 Database Tables** - Optimized with indexes
✅ **20 AJAX Endpoints** - Secure and validated
✅ **4 Page Templates** - Professional designs
✅ **6 Email Templates** - Responsive HTML
✅ **4 Cron Jobs** - Automated workflows
✅ **Full WooCommerce Integration** - Cart & checkout
✅ **Google Sheets Export** - OAuth2 integration
✅ **Activity Logging** - GDPR compliant
✅ **Purchase Order Generation** - Professional PDFs
✅ **Payment Integration** - Invoice generation

**Total Development Time:** 9 phases + 5 optional features
**Code Quality:** Production-ready, security-hardened
**Documentation:** Complete technical specifications

---

**Ready for QA Testing** ✅

---

## Document Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2025-11-09 | Initial comprehensive documentation |

---

**End of Documentation**
