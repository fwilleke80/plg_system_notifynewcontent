# Notify on new content
## Joomla! 5 plugin

### What is it?
This plugin helps keeping your users up to date when you post new content to your Joomla! website by sending each user a notification email.

### What does it do?
On installation, this plugin creates a custom user field "Notify on new content". Users can choose in their profile whether they want to receive an email.

When an article is created or edited, an additional tab "Notify users" is displayed (it should automatically be checked if the article is in a monitored category). Check or uncheck the checkbox in it. If checked, all users that have "Notify on new content" enabled will receive an email.

### Configuration
Go to *Extensions >> Plugins* to configure the plugin. You can e.g. select the category to monitor, and compose the mail subject and body.

#### Mail template
For the mail body you can use the following placeholders:

* `{ARTICLE_PUBLISH_DATE}` - The date when the article was published
* `{ARTICLE_MODIFIED_DATE}` - The date when the article was modified
* `{ARTICLE_CATEGORY}` - The name of the article's category
* `{ARTICLE_TITLE}` - The title of the article
* `{ARTICLE_LINK}` - The SEF link to the article
* `{ARTICLE_INTROTEXT}` - The article's intro text
* `{USERNAME}` - The name of the user who gets the mail

### Known issues
* You have to manually set the custom user field "Notify on new content" editable by the users, if you want them to be able to choose.
* The "Notify users" checkbox in the article form is not reliably checked by default (especially not in the frontend form), if the target category is a monitored category.

### License
Published under GNU Public License 2 (see LICENSE.txt).