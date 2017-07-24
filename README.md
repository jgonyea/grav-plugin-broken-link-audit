# Broken Link Checker Plugin

The **Broken Link Checker** Plugin is for [Grav CMS](http://github.com/getgrav/grav). It finds broken relative links within the site.

## Installation

Installing the Broken Link Checker plugin can be done in one of two ways. The GPM (Grav Package Manager) installation method enables you to quickly and easily install the plugin with a simple terminal command, while the manual method enables you to do so via a zip file.

### GPM Installation (Preferred)

The simplest way to install this plugin is via the [Grav Package Manager (GPM)](http://learn.getgrav.org/advanced/grav-gpm) through your system's terminal (also called the command line).  From the root of your Grav install type:

    bin/gpm install broken-link-checker

This will install the Broken Link Checker plugin into your `/user/plugins` directory within Grav. Its files can be found under `/your/site/grav/user/plugins/broken-link-checker`.

### Manual Installation

To install this plugin, just download the zip version of this repository and unzip it under `/your/site/grav/user/plugins`. Then, rename the folder to `broken-link-checker`. You can find these files on [GitHub](https://github.com/jeremy-gonyea/grav-plugin-broken-link-checker) or via [GetGrav.org](http://getgrav.org/downloads/plugins#extras).

You should now have all the plugin files under

    /your/site/grav/user/plugins/broken-link-checker
	
> NOTE: This plugin is a modular component for Grav which requires [Grav](http://github.com/getgrav/grav) and the [Error](https://github.com/getgrav/grav-plugin-error) and [Problems](https://github.com/getgrav/grav-plugin-problems) to operate.

## Configuration

Before configuring this plugin, you should copy the `user/plugins/broken-link-checker/broken-link-checker.yaml` to `user/config/plugins/broken-link-checker.yaml` and only edit that copy.

Here is the default configuration and an explanation of available options:

```yaml
enabled: true
inspection_level: raw
```
### Inspection_level
There are two options, ```raw``` and ```rendered```.  Currently, only the raw inspection is functioning for broken links (see To Do for future plans).


## Link Types Checked
** List here **

## Link Types Not Checked
** List here **


## Usage

After installing the plugin, visit admin/broken-links (or click on the new Admin menu item).  The first time visiting the report will display a button

## Known limitations

- Anchor links within a page are not checked.
- Links within twig are not checked.

## To Do

- Standardize terminology of broken links/ broken link/ etc.
- Complete the languages.yaml file.
- If possible, add feature to admin/tools.
- Add Rendered content processing.
- Add a CLI interface to generate the broken links list.
- Add ability to update a link from report.
- Possibly leverage 404 log via logerrors plugin.
- Create new report based grouped not by pages, but on the broken referenced link
