# Automad Comments Extension

A simple, flat-file comment system for Automad. Comments are stored as protected PHP files directly in the respective page directories. 

Allows visitors to leave comments on pages and notifies admin by email. Offers basic Markdown support and a bit of ootb configurations through the theme snippet parameters.

This extension is work in progress and only offers a basic set of features.



![ ](https://raw.githubusercontent.com/OliverWeinhold/automadcomments/refs/heads/master/image.jpg)

## Features

- **Flat-file storage**: Uses JSON data within protected `comments.php` files per page.
- **Spam & Security**:
  - **Honeypot**: Hidden field bot trap.
  - **Timestamping**: Enforces a minimum time before submission.
  - **Rate Limiting**: Limits submissions by email to once every 10 minutes.
  - **CSRF Tokens**: Prevents duplicate or forged submissions.
- **Formatting**: Basic Markdown support (bold, italic, lists)
- **Performance**: Loads 30 comments server-side with all additional entries fetched via AJAX.
- **Styling**: Minimal CSS styling with extra classes for easy theme integration.

## Usage

Embed the tag in your template file:

```php
<@ oliverweinhold/automadcomments @>
```

### Configuration Options

| Option | Default | Description |
|---|---|---|
| `notify_email` | `''` | Admin email address for new comment alerts. |
| `sort` | `'asc'` | `'asc'` (oldest first) or `'desc'` (newest first). |
| `max_height` | `''` | CSS max-height (e.g., `'400px'`) for the comment list. |
| `max_chars` | `2000` | Character limit per comment. |

### Full Configuration Example

Copy and paste this snippet to customize every aspect of the extension:

```php
<@ oliverweinhold/automadcomments { 
    notify_email: 'admin@example.com',
    sort: 'desc',
    max_height: '400px',
    max_chars: 2000,
    label_name: 'Name',
    label_email: 'Email',
    label_message: 'Message',
    label_website: 'Website',
    button_submit: 'Submit Comment',
    title_comments: 'Comments',
    placeholder_empty: 'No comments yet.',
    success_message: 'Thank you for your contribution!',
    error_fill_all: 'Please fill in all fields.',
    error_email_invalid: 'Invalid email address.',
    error_rate_limit: 'Please wait 10 minutes before posting again.',
    error_max_chars: 'Your comment is too long (max 2000 characters).',
    error_save: 'Error saving comment.',
    scroll_info: '↓ Show more ↓'
} @>
```

## License

MIT License - see [LICENSE](LICENSE)

## Author

[Oliver Weinhold](https://oliverweinhold.de/)
