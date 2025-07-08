# WP Church Service Plan

A modern WordPress plugin for planning and managing church services, including team assignments, file uploads, and a responsive tabbed interface.

## Features

- **Service Planning:** Create, edit, and view church service plans with all relevant roles and information.
- **Team Management:** Assign team members to each role (e.g., moderation, music, kids ministry, tech, etc.).
- **Responsive Tabbed Form:** Modern, mobile-friendly tab navigation for all service fields and uploads.
- **File Uploads:** Upload and manage files for each service entry, assign files to teams, and provide download links.
- **Central Upload Tab:** All uploads for a service are shown in a dedicated tab, grouped by team.
- **Upload Only in Edit Mode:** File uploads are only possible when editing an existing entry.
- **Admin Team Settings:** Manage available team members for each role via a WordPress admin menu.
- **Dark Mode Table View:** Stylish, accessible list view of all services with DataTables integration.

## Installation

1. Copy the plugin folder to your WordPress `wp-content/plugins` directory.
2. Activate the plugin in the WordPress admin area.
3. (Optional) Configure team members under **Church Service Teams** in the admin menu.

## Usage

- Use the shortcode `[church_service_list edit=true]` to display the service list with edit links.
- Use the shortcode `[church_service_form]` to display the service planning form (for creating or editing entries).
- In the form, use the **Uploads** tab to upload files for the current service (only available in edit mode).

## Database

- `wp_church_service_plan`: Stores all service entries and their fields.
- `wp_church_service_uploads`: Stores uploaded files, their team assignment, and links to the service entry.

## Notes

- File uploads are only possible after saving a new entry (edit mode).
- After uploading or saving, the plugin redirects to ensure the upload list is always up-to-date.
- If your theme outputs content before the plugin, the plugin uses a JavaScript redirect as fallback.

## License

MIT License

---

**Developed by Uwe & GitHub Copilot**
