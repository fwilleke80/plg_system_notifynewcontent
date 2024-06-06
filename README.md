# Notify on new content
## Joomla! 5 plugin

### What is it?
This plugin helps keeping your users up to date when you post new content to your Joomla! website by sending each user a notification email.

### What does it do?
On installation, this plugin creates a custom user field "Notify on new content", where users can choose whether they want to receive an email.

When a new article is published, all users that have "Notify on new content" enabled will receive an email.

### Configuration
* Set `$targetCategoryId` in `notifynewcontent.php` to the ID of a category. Users will be notified only if the an article has been pubished to this category (or its child categories).
* Maybe adjust the strings in the .ini files to your liking

### Known issues
* You have to set the custom user field "Notify on new content" editable by the users, if you want them to be able to choose.

### License
Published under GNU Public License 2 (see LICENSE.txt).