# TAPP Campaigns WordPress Plugin - Functional Requirements Document

**Version:** 1.0.0  
**Date:** January 2025  
**Purpose:** Requirements specification for WordPress plugin development  
**Target:** Enterprise-scale campaign management for internal teams

---

## 1. PROJECT OVERVIEW

### 1.1 What We're Building

A WordPress plugin that enables organizations to run time-boxed, invite-only product selection campaigns for internal teams. Think of it as an internal "flash sale" system where managers create campaigns, invite team members, and collect product selections during a specific time window.

### 1.2 Key Use Cases

- **Corporate Uniform Programs:** Employees select their work attire within a budget
- **Sales Incentives:** Top performers choose rewards from a curated product list
- **Team Building:** Groups order team merchandise or company swag
- **Seasonal Campaigns:** Quarterly or annual product allocation to departments
- **Employee Benefits:** Managed perk selection (holiday gifts, wellness items, etc.)

### 1.3 Scale Requirements

- **Users:** 1,000 - 10,000 concurrent users
- **Campaigns:** Up to 50 active campaigns simultaneously
- **Participants:** 1,000+ per campaign
- **Performance Target:** Page load under 2 seconds
- **Mobile:** Must work perfectly on iOS and Android

---

## 2. CRITICAL ARCHITECTURE DECISIONS

### 2.1 🚨 FRONTEND-ONLY MANAGEMENT (CRITICAL!)

**REQUIREMENT:** Managers, CEOs, Department Heads NEVER access WordPress admin (/wp-admin).

**ALL campaign management happens on the live website frontend:**
- Creating campaigns
- Editing campaigns
- Adding participants
- Viewing analytics
- Exporting data
- Managing templates
- Configuring settings (for their scope)

**WordPress Admin (/wp-admin) is ONLY for:**
- Site administrators (actual WordPress admins)
- Initial plugin setup
- Global settings that affect all campaigns
- Debugging and maintenance

**Implementation Note:** Create a complete frontend management dashboard, probably at `/my-account/campaigns/` or similar URL structure using WooCommerce My Account as the base.

### 2.2 Team vs Sales Campaigns - Completely Separate

**NOT a toggle or dropdown.** These are fundamentally different campaign types with:
- Separate creation workflows
- Separate listing pages
- Different default settings
- Different templates

Users should click "Create Team Campaign" or "Create Sales Campaign" as two distinct actions.

### 2.3 Theme Integration - Not Isolated

**Must follow the active WordPress theme's design:**
- Use theme's container classes
- Adopt theme's typography
- Match theme's color scheme
- Use theme's button styles
- Respect theme's spacing system

**Specifically test with Woodmart theme** as that's the primary target.

### 2.4 Database Design

**Need 5 custom tables:**
1. Campaigns table (core campaign data)
2. Campaign-Products junction (which products in which campaign)
3. Participants table (who's invited to which campaign)
4. Responses table (user selections with version tracking)
5. Campaign meta table (flexible key-value storage)

**Performance requirements:**
- Proper indexes on frequently queried columns
- Support for 10,000 users without slowdown
- Efficient queries (no N+1 problems)
- Pagination for large datasets

---

## 3. USER ROLES & PERMISSIONS

### 3.1 Role Definitions

**Roles come from TAPP Onboarding Plugin:**
```
- Administrator (WordPress admin)
- CEO (executive access)
- Manager (creates and manages campaigns)
- Staff (participates in campaigns only)
```

**Department Information:**
User meta keys checked in priority order:
1. `tapp_department`
2. `department`
3. `user_department`

Supports both:
- **Flat structure:** Marketing, Sales, IT, HR
- **Hierarchical:** APAC → Sales → Team A

### 3.2 Permission Matrix

| Action | Admin | CEO | Manager | Staff |
|--------|-------|-----|---------|-------|
| Access WP Admin | ✅ | ❌ | ❌ | ❌ |
| Create Campaigns (frontend) | ✅ | ✅ | ✅ | ❌ |
| Edit Own Campaigns | ✅ | ✅ | ✅ | ❌ |
| Edit Others' Campaigns | ✅ | ✅ | ❌ | ❌ |
| View All Campaigns | ✅ | ✅ | ❌ | ❌ |
| View Department Campaigns | ✅ | ✅ | ✅ | ❌ |
| Delete Campaigns | ✅ | ✅ | ✅* | ❌ |
| Export Data | ✅ | ✅ | ✅ | ❌ |
| View Analytics | ✅ | ✅ | ✅ | ❌ |
| Edit User Submissions | ✅ | ✅ | ✅ | ❌ |
| Participate in Campaigns | ✅ | ✅ | ✅ | ✅ |
| Access Global Settings | ✅ | ❌ | ❌ | ❌ |

*Managers can only delete their own campaigns

### 3.3 Department-Level Restrictions

- Managers see only their department's campaigns
- CEO sees all departments
- Cross-department campaigns possible (CEO can create)
- Department heads see their team's participation

---

## 4. CAMPAIGN LIFECYCLE

### 4.1 Campaign States

**Draft:** Being created, not visible to anyone except creator
**Scheduled:** Published but start date not reached yet
**Active:** Currently running, participants can respond
**Ended:** End date passed or manually ended, read-only
**Archived:** Moved to archive, hidden from main lists but data retained

### 4.2 State Transitions

```
Draft → Scheduled (on publish with future start date)
Draft → Active (on publish with past/current start date)
Scheduled → Active (automatically at start_date)
Active → Ended (automatically at end_date OR manually by manager)
Ended → Archived (manually by manager/CEO, or auto after X days)
Archived → Restored (back to Ended state)
```

### 4.3 Campaign Properties

**Basic Information:**
- Campaign Name (required)
- Campaign Type: Team OR Sales (determined by creation path)
- Start Date & Time (required)
- End Date & Time (required)
- Notes (short message, optional, shown in banner)
- Description (long HTML content, optional, expandable section)
- Department (optional, for filtering/permissions)

**Selection Rules:**
- Selection Limit (max items per user, default: 1)
- Minimum Selections (validation, default: 0)
- Edit Policy: Once / Multiple / Until End
- Color/Size Mode: Combined / Separate

**Product Configuration:**
- Product List (from WooCommerce)
- Display Order (drag and drop)
- Color Configuration:
  - All Colors (user selects any available)
  - Specific Colors (campaign creator pre-selects which colors available)
  - Single Color (only one color, auto-selected)

**Advanced Options:**
- Payment Enabled (redirect to checkout after submission)
- Generate Purchase Order on campaign end
- Generate Invoice on campaign end (only if payment enabled)
- Invoice Recipients (email addresses, only visible if payment enabled)

**Notifications:**
- Send invitation email on publish
- Send confirmation email on submission
- Send reminder email X hours before end (default: 24)
- Webhook URL (optional, for external integrations)

---

## 5. FRONTEND CAMPAIGN MANAGEMENT SYSTEM

### 5.1 Manager Dashboard Location

**Primary Access Point:** `/my-account/campaigns/` or `/my-account/campaign-manager/`

This is a NEW tab in WooCommerce My Account area, visible only to Managers, CEOs, and Admins.

### 5.2 Dashboard Features

**Top Stats Cards:**
- Active Campaigns Count
- Total Participants (across all campaigns)
- Pending Responses Count
- This Month's Campaigns

**Campaign List:**
- Searchable table
- Filters: Status, Type, Date Range, Department
- Sortable columns: Name, Type, Status, Progress, End Date
- Each row shows: Campaign name, type badge, status, progress bar, actions

**Quick Actions per Campaign:**
- View Campaign (open campaign page)
- View Stats (detailed statistics modal/page)
- Edit Campaign (edit form)
- End Early (confirmation dialog)
- Send Reminders (to pending participants)
- Export Audience CSV
- Export Responses CSV
- Export Summary CSV
- Generate Invoice (if payment enabled)
- Download Purchase Order
- Archive/Delete

### 5.3 Campaign Creation Workflow (Frontend)

**Two Entry Points:**
1. "Create Team Campaign" button → Team campaign form
2. "Create Sales Campaign" button → Sales campaign form

**Multi-Step Form:**

**Step 1: Basic Information**
- Campaign name
- Start and end dates (date/time pickers)
- Notes (textarea)
- Description (rich text editor)
- Department selection (dropdown)

**Step 2: Select Products**
- Search WooCommerce products
- Filter by category
- Multi-select with checkboxes
- Show product image, name, SKU, price
- Drag to reorder selected products
- "Add from Template" option

**Step 3: Configure Selection Rules**
- Selection limit (number input with explanation)
- Minimum selections (validation)
- Edit policy (radio buttons with descriptions)
- Color/Size mode (radio buttons)
- Color configuration:
  - If mode = specific/single, show color picker/selector

**Step 4: Add Participants**
- Manual user search (AJAX search)
- CSV upload (download sample format)
- Select entire department (dropdown)
- Select from user group (if groups created)
- Show participant list with remove option

**Step 5: Payment & Notifications**
- Payment toggle
- Invoice recipients field (only if payment enabled)
- Email notifications toggles
- Reminder timing (number input in hours)
- Webhook URL (optional)

**Step 6: Preview & Publish**
- Preview campaign as participant would see it
- Generate shareable preview link (for testing)
- Save as Draft
- Schedule for future date
- Publish immediately

### 5.4 Campaign Editing (Frontend)

**Access:** From dashboard, click "Edit" on any campaign

**Rules:**
- Can edit scheduled or active campaigns
- Cannot change type (Team/Sales)
- Editing active campaign shows warning
- Can add more participants anytime
- Can extend end date
- Cannot shorten end date if already ended
- Changes log in activity log

### 5.5 Campaign Analytics (Frontend)

**Statistics Modal/Page:**

**Overview Section:**
- Total invited
- Total submitted
- Pending count
- Participation rate percentage
- Average response time

**Progress Over Time:**
- Line chart showing submissions per day
- Target line (if set)

**Product Summary Table:**
| Product | SKU | Color | Size | Total Qty | Users |
|---------|-----|-------|------|-----------|-------|
| T-Shirt | ABC | Red   | M    | 150       | 150   |
| T-Shirt | ABC | Red   | L    | 200       | 200   |

**Participant List with Actions:**
| Name | Email | Department | Status | Submitted At | Actions |
|------|-------|------------|--------|--------------|---------|
| John | john@ | Sales | Submitted | 2h ago | View / Edit / Delete |
| Jane | jane@ | Sales | Pending | - | Remind / Remove |

**View Individual Response:**
- Click "View" → See user's selections
- Shows: Product, Color, Size, Quantity
- Shows submission timestamp
- If multiple versions, show version selector

**Edit Individual Response:**
- Click "Edit" → Inline editor or modal
- Manager can change selections
- Confirmation: "This will notify the user"
- Save → Logs activity + sends email to user
- Reason field (optional): "Why are you editing?"

**Delete Individual Response:**
- Click "Delete" → Confirmation dialog
- "Are you sure? This cannot be undone."
- Deletes all versions of that user's response
- Logs activity

---

## 6. STAFF PARTICIPATION FLOW

### 6.1 Campaign Discovery

**How Staff Find Campaigns:**
1. Email invitation with direct link
2. My Account → My Campaigns tab (lists all campaigns they're invited to)
3. Banner notification on site (optional)

### 6.2 Accessing Campaign

**URL Structure:** `/campaign/campaign-name/` (pretty permalinks)

**Access Control:**
- Not logged in → Redirect to login page (with return URL)
- Logged in but not invited → Friendly message: "This campaign is invite-only. Contact your manager if you believe you should have access."
- Campaign not started yet → "Countdown page" showing when it starts
- Campaign ended → Read-only view of their selections

### 6.3 Campaign Page Layout (Active)

**Uses theme's structure:** `get_header()` → content → `get_footer()`

**Banner Section:**
- Campaign title (h1)
- Status chip (Active/Ending Soon)
- Notes (if exists, colored banner)
- Countdown timer:
  - BEFORE start: "Starts in: 2d 5h 30m 15s"
  - DURING campaign: "Ends in: 1d 2h 15m 45s"
  - Live JavaScript updates every second

**Description Section:**
- Full description content
- Expandable if long (Read More button)
- Can include images/videos

**Product Grid:**
- Uses theme's product card styles
- Responsive columns (1 on mobile, 2-4 on desktop)
- Each card shows:
  - Product image (lazy loaded)
  - Product name
  - SKU
  - Price (if payment enabled)
  - Color dropdown (based on color configuration)
    - If "all colors": show all available
    - If "specific colors": show only pre-selected colors
    - If "single color": auto-filled, disabled
  - Size dropdown (WooCommerce variations)
  - Size Guide button (opens modal with size chart from PDP)
  - Quantity selector (default: 1, range: 1-10)
  - Checkbox: "Select this product"

**Controls:**
- Search bar (live filtering by name/SKU)
- Category filter dropdown
- Sort dropdown: A-Z, Z-A, Price Low-High, Price High-Low, Newest
- View toggle: Grid / List (optional)
- Selection counter (sticky): "3 / 5 selected"

**Submission:**
- "Review Selections" button (sticky at bottom)
- Disabled until valid selection (min to max range)
- Click → Modal opens showing summary
- Modal shows all selections with thumbnails
- "Confirm & Submit" button
- Success message
- If payment enabled: "Redirecting to checkout..."

### 6.4 Editing Response

**If Edit Policy Allows:**
- User returns to campaign page
- Previous selections are pre-checked
- Can modify and resubmit
- Saves as new version (version 2, 3, etc.)
- Previous versions archived but kept
- Sends "Response Updated" email

**If Edit Policy = Once:**
- Campaign page shows read-only view
- Message: "You have already submitted. No edits allowed."
- Shows submitted selections

### 6.5 Campaign States from User Perspective

**Scheduled (Not Started):**
- Shows countdown: "Campaign starts in..."
- Shows product preview (disabled, can't select)
- Shows description
- No submission button

**Active:**
- Full campaign page with selection functionality
- Countdown: "Campaign ends in..."
- Can submit/edit (based on edit policy)

**Ended:**
- Read-only view
- Message: "This campaign has ended. Thank you for participating!"
- Shows user's final selections
- No ability to edit

---

## 7. MY ACCOUNT INTEGRATION

### 7.1 New Tab: "My Campaigns"

**Visible to:** All users (CEO, Manager, Staff)

**Tab Location:** Between "Orders" and "Downloads" (or configurable)

**Table Columns:**
- Campaign Name
- Type (Team/Sales badge)
- Department
- Status (color-coded chip)
- End Date
- My Response (Submitted ✓ / Pending ⏳)
- Action (button: "View" or "Respond")

**Filters:**
- Status: All / Active / Ended / Archived
- Type: All / Team / Sales
- Response Status: All / Submitted / Pending

**Clicking "View" or "Respond":**
- Opens campaign page
- If active and not submitted: Shows campaign with selection UI
- If active and submitted: Shows campaign with edit option (if allowed)
- If ended: Shows read-only view with their selections

### 7.2 New Tab: "Campaign Manager" (Managers/CEOs Only)

**This is the main management dashboard** described in Section 5.

**Not visible to Staff.**

---

## 8. EMAIL NOTIFICATION SYSTEM

### 8.1 Email Types

**1. Invitation Email**
- **Trigger:** User added to campaign
- **Recipient:** New participant
- **Subject:** "[Company] You're Invited to Campaign: {Campaign Name}"
- **Content:**
  - Greeting with user's name
  - Campaign name and type
  - Start and end dates
  - Selection limit
  - Notes (if exists)
  - Big button: "View Campaign"
  - Footer: Company info, contact manager

**2. Confirmation Email**
- **Trigger:** First submission
- **Recipient:** Participant who submitted
- **Subject:** "[Company] Response Confirmed: {Campaign Name}"
- **Content:**
  - Thank you message
  - List of selected products with details
  - Submission timestamp
  - Link to view campaign
  - If payment enabled: "Your order is being processed"

**3. Edit Notice Email**
- **Trigger:** Response edited by user OR manager
- **Recipient:** Participant
- **Subject:** "[Company] Response Updated: {Campaign Name}"
- **Content:**
  - Notification of update
  - If manager edited: "Your manager updated your response"
  - Link to view updated response

**4. Reminder Email**
- **Trigger:** X hours before campaign end (configurable, default: 24)
- **Recipient:** Participants who haven't submitted
- **Subject:** "[Company] Reminder: Campaign Ending Soon - {Campaign Name}"
- **Content:**
  - Urgent tone
  - Campaign ending in X hours
  - Deadline emphasis
  - "Don't miss out" messaging
  - Big button: "Submit Response Now"

**5. Campaign Ended Email**
- **Trigger:** Campaign reaches end date
- **Recipient:** All participants
- **Subject:** "[Company] Campaign Ended: {Campaign Name}"
- **Content:**
  - Thank you message
  - Summary of their selections
  - Next steps (if any)

**6. Manager Notification Email**
- **Trigger:** Configurable (submission, target reached, campaign ended)
- **Recipient:** Campaign creator + CC list
- **Subject:** "[Company] Campaign Update: {Campaign Name}"
- **Content:**
  - Stats: X/Y submitted
  - Participation rate
  - Link to dashboard

### 8.2 Email Configuration

**Global Settings (Admin Only):**
- From Name
- From Email
- Logo URL (displayed in email header)
- Brand Color (for buttons and header)
- Footer Text
- Social Media Links (optional)

**Per-Campaign Overrides:**
- Custom sender name
- CC recipients
- Custom footer message

**Template Customization:**
- Visual editor for each email type
- Variable insertion: {user_name}, {campaign_name}, etc.
- Preview with sample data
- Send test email
- Reset to default template

---

## 9. REPORTING & ANALYTICS

### 9.1 Campaign-Level Reports (Frontend Dashboard)

**Real-Time Stats:**
- Live participant count
- Live submission count
- Live pending count
- Participation rate percentage
- Time remaining

**Historical Charts:**
- Submissions over time (line chart)
- Participation by department (bar chart)
- Product popularity (pie chart)
- Response time distribution (histogram)

**Department Breakdown:**
- If campaign has multiple departments
- Shows each department's participation
- Comparison table

### 9.2 Export Options

**Audience Export (CSV):**
```
Campaign Name, User ID, Name, Email, Department, Status, Invited At, Submitted At, Submission Count
```

**Responses Export (CSV):**
```
Campaign Name, User ID, Name, Email, Department, Product ID, Product Name, SKU, Color, Size, Quantity, Submitted At, Version
```

**Summary Export (CSV):**
```
Campaign Name, Product ID, Product Name, SKU, Color, Size, Total Quantity, Number of Users
```
Pre-aggregated data for procurement

**PDF Report:**
- Executive summary page
- Charts included
- Product breakdown table
- Formatted for printing
- Includes company logo

**Google Sheets Export (Optional Feature):**
- Real-time sync
- Auto-updates as submissions come in
- Shared with specified users
- Separate sheets for Audience, Responses, Summary

### 9.3 Analytics Dashboard (Optional Feature)

**Global Analytics (CEO/Admin):**
- Total campaigns (all time)
- Total participants (all time)
- Average participation rate
- Most popular products (across all campaigns)
- Department engagement comparison
- Trends over time

**Date Range Filter:**
- Last 7 days
- Last 30 days
- Last 90 days
- This quarter
- This year
- Custom range

**Comparison Mode:**
- Compare 2 campaigns side by side
- Compare time periods
- Department vs department

---

## 10. PRODUCT SELECTION SPECIFICS

### 10.1 WooCommerce Integration

**Product Types Supported:**
- Simple products
- Variable products (with attributes)
- Grouped products (each item selectable separately)

**Not Supported:**
- External/affiliate products
- Virtual/downloadable products (unless specifically enabled)

### 10.2 Variation Handling

**Auto-Detection:**
- Plugin detects WooCommerce product attributes
- Common attributes: Color (pa_color), Size (pa_size), Material, Style
- Custom attributes supported

**Display Modes:**

**Combined Mode:**
- Single dropdown: "Red - Medium" as one option
- Simpler for users, fewer dropdowns
- Good for products with limited variations

**Separate Mode:**
- Individual dropdown per attribute
- Color dropdown (separate)
- Size dropdown (separate)
- More flexible, better for many variations

### 10.3 Color Configuration

**Mode: All Colors**
- User sees all available colors for the product
- No restriction

**Mode: Specific Colors**
- Campaign creator pre-selects colors: [Red, Blue, Green]
- User can ONLY select from those colors
- Other colors hidden from dropdown

**Mode: Single Color**
- Campaign creator selects ONE color: [Red]
- Color dropdown is disabled/auto-filled
- User just picks size

**Use Case Example:**
- Company wants everyone to get RED uniforms
- Set color mode to "Single" and select Red
- Users can't pick other colors

### 10.4 Size Guide Integration

**Source:** WooCommerce product's size chart
- Check product meta: `_size_chart_image` or `_size_chart_html`
- Fallback to global size chart
- Fallback to category size chart

**Display:**
- "Size Guide" button next to size dropdown
- Opens modal/lightbox
- Shows size chart image or HTML table
- Printable version available
- Mobile-optimized view

**User Preference Memory:**
- Remember user's last selected size
- Auto-select (but allow change)
- Show indicator: "Your usual size: M"

---

## 11. PAYMENT INTEGRATION

### 11.1 When Payment is Enabled

**Campaign Settings:**
- Toggle: "Require Payment"
- Shows price on product cards
- Shows total in selection summary

**After Submission:**
- Selected products added to WooCommerce cart
- Redirect to checkout
- Standard WooCommerce checkout flow
- Order placed

**Cart Integration:**
- Add campaign metadata to cart items
- Tag: "From Campaign: {Name}"
- Prevent editing quantities in cart
- Clear cart after purchase

### 11.2 Invoice Generation

**Only if Payment Enabled:**
- Auto-generate on campaign end
- Lists all orders/selections
- Shows subtotal per user
- Shows grand total
- PDF format
- Emails to configured recipients

**Invoice Recipients:**
- Admin email (default)
- Additional emails (comma-separated field)
- Campaign creator
- Finance team (if configured in settings)

**Invoice Contents:**
- Company letterhead (logo, address)
- Campaign name and dates
- Participant breakdown
- Product breakdown
- Totals
- Generated date and invoice number

---

## 12. ADVANCED FEATURES

### 12.1 Campaign Templates

**Purpose:** Save time when creating similar campaigns

**Creating Template:**
- From existing campaign: "Save as Template" button
- All settings saved except dates and participants
- Name the template
- Categorize: Team / Sales / Custom

**Using Template:**
- "Create from Template" button
- Select template from list
- All fields pre-filled
- Modify as needed
- Save as new campaign

**Template Management:**
- List all templates
- Edit template
- Delete template
- Preview template
- Clone template

### 12.2 User Groups

**Purpose:** Quickly add common sets of users

**Creating Group:**
- Name the group: "Sales Team - APAC"
- Search and add users
- Tag with department
- Save

**Using Group:**
- When adding participants
- "Add from Group" button
- Select group
- All group members added

**Group Management:**
- List all groups
- Edit members
- Delete group
- Export group as CSV

### 12.3 Campaign Scheduling & Conflicts

**Scheduling:**
- Create multiple campaigns with future dates
- Calendar view of scheduled campaigns
- Auto-activate at start_date (cron job)
- Auto-end at end_date (cron job)

**Conflict Detection:**
- When adding participants
- Check if user is already in another active campaign
- Warning: "50 users are busy in another campaign"
- Option to proceed or adjust dates

### 12.4 Webhook Integration

**Purpose:** Notify external systems of events

**Events:**
- `campaign.created`
- `campaign.started`
- `campaign.ended`
- `participant.invited`
- `response.submitted`
- `response.edited`
- `campaign.reminder_sent`

**Payload (JSON):**
```json
{
  "event": "response.submitted",
  "timestamp": "2025-01-15T10:30:00Z",
  "campaign_id": 123,
  "campaign_name": "Summer Collection",
  "user_id": 456,
  "user_email": "user@company.com",
  "data": {
    "selections": [...],
    "total_items": 5
  }
}
```

**Configuration:**
- Global webhook URL (affects all campaigns)
- Per-campaign override
- Choose which events to send
- Retry logic (3 attempts)
- Webhook log (success/failures)
- Test webhook button

### 12.5 Activity Log

**What Gets Logged (Admin Panel Only, NOT Frontend):**
- Campaign created (who, when, settings)
- Campaign edited (who, what changed, when)
- Campaign deleted (who, when)
- Campaign ended early (who, when, reason)
- Participant added (who added, who was added)
- Participant removed
- Response submitted (user, timestamp)
- Response edited (who edited, user affected)
- Manager override (manager who did it, user affected, changes)
- Data exported (who, what, when)
- Settings changed (who, what)

**Log Entry Structure:**
- Timestamp (precise to second)
- User (who performed action)
- Action type
- Target (campaign ID, user ID)
- Changes (old value → new value, JSON)
- IP Address (optional, can be anonymized for GDPR)

**Viewing Logs:**
- **Admin Only:** Access via WP Admin → Campaigns → Activity Log
- Paginated table
- Filters: Date range, user, action type, campaign
- Search functionality
- Export log as CSV
- Auto-delete old logs (configurable retention period)

**GDPR Compliance:**
- Option to anonymize IP addresses
- User data export (show all their logged actions)
- User data deletion (anonymize their name in logs)

### 12.6 Purchase Order Generation

**Trigger:**
- Campaign status changes to "ended"
- Or manual "Generate PO" button

**PO Contents:**
- Campaign name and details
- Product summary (SKU, quantity, totals)
- Department breakdown
- Cost estimates (if prices available)
- Participant list
- PO number (auto-generated)
- Generated date
- Company letterhead

**Format:**
- PDF document
- Professional formatting
- Printable
- Downloadable from dashboard

**Distribution:**
- Auto-email to procurement team (if configured)
- Downloadable from campaign dashboard
- Attached to campaign record

### 12.7 Smart Recommendations (Optional Feature)

**"Popular in Your Department":**
- Shows products frequently selected by user's department
- Based on previous campaign data
- Carousel or highlighted section
- Quick add button

**Logic:**
- Query responses table
- Filter by user's department
- Aggregate product selections
- Order by popularity
- Show top 5-10

**Display:**
- Separate section above main product grid
- "Trending" or "Popular" badge on products
- "X colleagues selected this" indicator

---

## 13. SETTINGS & CONFIGURATION

### 13.1 Global Settings (WP Admin Only)

**General Tab:**
- Plugin Enable/Disable toggle
- Default campaign duration (days)
- Timezone selection
- Date/time format
- Products per page

**Features Tab (Toggle Each):**
- ☑ Campaign Templates
- ☑ Preview Mode
- ☑ Smart Recommendations
- ☑ Product Search
- ☑ Size Guides
- ☑ Activity Log
- ☑ Analytics Dashboard
- ☐ Google Sheets Export
- ☑ PDF Reports
- ☑ Webhook Integration
- ☑ Purchase Orders
- ☑ Invoice Generation

**Notifications Tab:**
- Email from name
- Email from address
- Default reminder timing (hours)
- Email templates (editable)
- Global webhook URL
- Email logo URL
- Brand color

**Permissions Tab:**
- Role capability matrix
- Department-level permissions
- Campaign-specific access rules

**Advanced Tab:**
- Enable caching (toggle)
- Cache duration (seconds)
- AJAX polling interval
- Auto-archive campaigns after X days
- Log retention period
- GDPR compliance mode
- IP anonymization
- Debug mode

### 13.2 Per-Campaign Settings

**Overrides available:**
- Custom email sender
- Custom webhook URL
- Custom reminder timing
- Custom validation messages
- Preview mode enable/disable

---

## 14. SECURITY REQUIREMENTS

### 14.1 Authentication & Authorization

**Access Control:**
- Campaign pages: Login required (redirect to login)
- Participant check: Must be invited
- Manager actions: Capability check
- Admin settings: Admin only

**Session Security:**
- Use WordPress nonces for all forms
- Check capabilities before all actions
- Validate user permissions on every request
- AJAX requests require nonce verification

### 14.2 Data Validation & Sanitization

**Input Validation:**
- Email format check
- Date range validation
- Numeric inputs (selection limits, etc.)
- File uploads (CSV: validate format, size, content)

**Sanitization:**
- Text fields: Remove HTML, trim whitespace
- Email: Format validation
- URLs: Validate and sanitize
- HTML content: Allow only safe tags

**Output Escaping:**
- Always escape HTML output
- Escape attributes
- Escape JavaScript data
- Never trust user input

### 14.3 SQL Security

**Database Queries:**
- Always use prepared statements
- Never concatenate user input in queries
- Use WordPress $wpdb methods
- Sanitize table/column names
- Limit result sets

### 14.4 File Upload Security

**CSV Import:**
- Validate file type (.csv only)
- Check file size (max 5MB)
- Scan for malicious content
- Parse safely (use PHP's native CSV functions)
- Validate email format in each row
- Limit rows (max 10,000)

### 14.5 Rate Limiting

**Prevent Abuse:**
- Max 100 campaign invitations per hour
- Max 10 reminder emails per hour per campaign
- Max 60 API requests per minute per user
- Implement cooldown periods

### 14.6 GDPR Compliance

**User Rights:**
- Right to access (export all their data)
- Right to be forgotten (anonymize logs, delete responses)
- Right to data portability (CSV export)

**Data Handling:**
- Log retention policies
- IP anonymization option
- Consent tracking
- Data encryption (passwords, sensitive fields)

---

## 15. PERFORMANCE REQUIREMENTS

### 15.1 Page Load Targets

- Homepage: < 1 second
- Campaign page (20 products): < 2 seconds
- Campaign page (100 products): < 3 seconds
- Dashboard: < 2 seconds
- Analytics page: < 3 seconds

### 15.2 Database Optimization

**Required Indexes:**
- campaigns: status, start_date, end_date, creator_id
- participants: campaign_id + user_id (composite), email
- responses: campaign_id + user_id + is_latest (composite)

**Query Optimization:**
- Use pagination (limit/offset)
- Avoid N+1 queries (batch fetch)
- Use transients for expensive queries
- Cache frequent queries

### 15.3 Caching Strategy

**Object Cache:**
- Campaign objects (1 hour)
- Product data (30 minutes)
- User groups (1 hour)
- Department structure (24 hours)

**Transient API:**
- Dashboard stats (5 minutes)
- Analytics data (15 minutes)
- Product availability (1 minute)

**Cache Invalidation:**
- Clear on campaign edit
- Clear on response submission
- Clear on settings change

### 15.4 Asset Optimization

**CSS/JavaScript:**
- Minified versions for production
- Conditional loading (only on relevant pages)
- Defer non-critical JS
- Inline critical CSS

**Images:**
- Lazy loading
- Responsive images (srcset)
- WebP format
- Thumbnail sizes

**AJAX:**
- Debounce search inputs
- Batch API requests
- Reduce polling frequency (30s intervals, not 1s)

---

## 16. MOBILE & RESPONSIVE DESIGN

### 16.1 Mobile Requirements

**Must Work Perfectly On:**
- iOS 14+ (Safari, Chrome)
- Android 10+ (Chrome, Samsung Browser)

**Touch Optimization:**
- Button minimum size: 44x44px
- Swipe gestures for product carousel
- Touch-friendly dropdowns
- No hover-dependent interactions

**Mobile-Specific Features:**
- Bottom sheet for filters (not sidebar)
- Sticky header with key actions
- Collapsible sections
- Thumb-friendly navigation

### 16.2 Responsive Breakpoints

**Desktop:** > 1200px (4 columns)
**Tablet:** 768px - 1199px (2-3 columns)
**Mobile:** < 767px (1-2 columns)

**Layout Adaptations:**
- Product grid: Adjusts columns
- Navigation: Hamburger menu on mobile
- Tables: Horizontal scroll or card view
- Forms: Full-width inputs on mobile
- Modals: Full-screen on mobile

---

## 17. INTERNATIONALIZATION

### 17.1 Translation Readiness

**Text Domain:** `tapp-campaigns`

**All Strings:**
- Wrapped in translation functions
- Use `__()`, `_e()`, `_n()` for singular/plural
- Use `_x()` for context
- JavaScript strings via wp_localize_script

**Translation Files:**
- Generate .pot file
- Support for .po/.mo files
- Compatible with Loco Translate plugin
- WPML/Polylang support

### 17.2 RTL Support

**RTL Languages:**
- Arabic
- Hebrew
- Urdu

**Implementation:**
- Automatic RTL CSS loading
- Mirrored layouts
- Right-aligned text
- Reversed navigation and icons

### 17.3 Date/Time Localization

**Format Respect:**
- Use WordPress date/time settings
- Timezone awareness
- Relative time: "2 hours ago"
- Countdown in user's timezone

---

## 18. TESTING REQUIREMENTS

### 18.1 Functional Testing

**Test Scenarios:**
- Complete campaign lifecycle (create → active → end)
- User role permissions (each role)
- Department restrictions
- Payment flow (if enabled)
- Email delivery
- CSV import/export
- Webhook triggers
- Activity logging

### 18.2 Performance Testing

**Load Testing:**
- 10,000 concurrent users
- 50 active campaigns
- 1,000 participants per campaign
- Database query performance
- Page load times under load

### 18.3 Security Testing

**Audit For:**
- SQL injection attempts
- XSS vulnerabilities
- CSRF bypasses
- Authentication bypasses
- Permission escalation
- File upload exploits

### 18.4 Compatibility Testing

**Browser Testing:**
- Chrome (latest 2 versions)
- Firefox (latest 2 versions)
- Safari (latest 2 versions)
- Edge (latest 2 versions)
- Mobile browsers

**Theme Testing:**
- Woodmart (primary)
- Storefront
- Astra
- Default WordPress themes

**Plugin Conflicts:**
- Test with common plugins
- WooCommerce extensions
- Caching plugins
- Security plugins

---

## 19. SUCCESS CRITERIA

### The plugin is complete and successful when:

**Core Functionality:**
1. ✅ Manager can create campaign entirely from frontend (no /wp-admin access)
2. ✅ Team and Sales campaigns are accessed via separate buttons/pages
3. ✅ Campaign uses active theme's styling (integrated, not isolated)
4. ✅ Non-logged-in users redirect to login with return URL
5. ✅ Countdown timer shows "Starts in" before and "Ends in" during campaign
6. ✅ Color configuration works (all/specific/single modes)
7. ✅ Staff can select products and submit response
8. ✅ Submission saves to database with version tracking
9. ✅ Emails send automatically (invitation, confirmation, reminder)
10. ✅ Campaign automatically ends at end_date

**Management Features:**
11. ✅ Manager dashboard shows all campaigns with real-time stats
12. ✅ Manager can view individual user responses
13. ✅ Manager can edit any user's response from frontend
14. ✅ Manager can delete user responses
15. ✅ Manager can export CSVs (audience, responses, summary)
16. ✅ Activity log tracks all actions (admin panel only)
17. ✅ Settings page controls features via toggles

**User Experience:**
18. ✅ My Account shows "My Campaigns" tab for all users
19. ✅ "Campaign Manager" tab visible only to managers/CEOs
20. ✅ Ended campaigns are read-only for participants
21. ✅ Invoice generates only when payment enabled
22. ✅ Mobile works perfectly on iOS and Android
23. ✅ Works with Woodmart theme without styling conflicts

**Performance & Security:**
24. ✅ Handles 1,000 participants per campaign
25. ✅ Page load < 2 seconds
26. ✅ All forms have nonces
27. ✅ All inputs sanitized
28. ✅ All outputs escaped
29. ✅ SQL injection protected (prepared statements)
30. ✅ Translation-ready (text domain used)

---

## 20. OUT OF SCOPE

**Features NOT included in v1.0:**
- ❌ Inventory management (stock reservation, tracking)
- ❌ Mobile apps (iOS/Android native)
- ❌ Gamification (points, badges)
- ❌ Social sharing features
- ❌ Video product showcases
- ❌ Live chat integration
- ❌ Multi-tenant support
- ❌ SSO integration
- ❌ AI recommendations
- ❌ Predictive analytics

**These may be added in future versions.**

---

## 21. GLOSSARY

**Campaign:** A time-boxed event where selected users can choose products from a curated list.

**Participant:** A user who has been invited to a campaign.

**Response:** A participant's product selections within a campaign.

**Selection Limit:** Maximum number of products a participant can choose.

**Edit Policy:** Rules determining if/when participants can modify their response.

**Color Configuration:** Campaign creator's control over which colors participants can select.

**Version Tracking:** System that saves each submission as a new version, keeping history.

**Manager Dashboard:** Frontend interface where managers create and monitor campaigns.

**Activity Log:** Record of all actions taken, visible only in admin panel.

**Department:** Organizational unit (e.g., Sales, Marketing) used for permissions and filtering.

**Template:** Saved campaign configuration that can be reused.

**User Group:** Pre-defined set of users that can be quickly added to campaigns.

**Webhook:** HTTP callback that notifies external systems of campaign events.

**Purchase Order:** Document listing all selected products for procurement.

**Invoice:** Payment document generated for campaigns with payment enabled.

---

## 22. FINAL NOTES FOR IMPLEMENTATION

### Architecture Decisions

1. **Frontend-First:** Build complete frontend management system before admin panel
2. **Theme Integration:** Study Woodmart's classes and structure
3. **Database Design:** Optimize for scale (proper indexes critical)
4. **AJAX-Heavy:** Most interactions should be AJAX (no page reloads)
5. **Progressive Enhancement:** Core functionality works without JavaScript

### Development Priority

**Phase 1:** Database + Core Classes
**Phase 2:** Frontend Manager Dashboard
**Phase 3:** Campaign Creation & Editing
**Phase 4:** Campaign Participation Flow
**Phase 5:** Email & Notifications
**Phase 6:** Analytics & Reporting
**Phase 7:** Advanced Features
**Phase 8:** Polish & Testing

### Code Quality Standards

- Follow WordPress Coding Standards
- Use WordPress functions (never reinvent)
- Comment complex logic
- Use meaningful variable names
- Write modular, reusable code
- Plan for extensibility (hooks & filters)

---

**END OF FUNCTIONAL REQUIREMENTS DOCUMENT**

This document contains ZERO implementation code - only requirements and specifications. Architect and implement as you see fit while meeting all requirements.