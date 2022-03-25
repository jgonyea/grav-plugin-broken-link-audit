# Broken Link Audit Plugin

The **Broken Link Audit** Plugin is for [Grav CMS](http://github.com/getgrav/grav). It finds broken relative links within the site.

## Installation

Installing the Broken Link Audit plugin can be done in one of two ways. The GPM (Grav Package Manager) installation method enables you to quickly and easily install the plugin with a simple terminal command, while the manual method enables you to do so via a zip file.

### GPM Installation (Preferred)

The simplest way to install this plugin is via the [Grav Package Manager (GPM)](http://learn.getgrav.org/advanced/grav-gpm) through your system's terminal (also called the command line).  From the root of your Grav install type:

    bin/gpm install broken-link-audit

This will install the Broken Link Audit plugin into your `/user/plugins` directory within Grav. Its files can be found under `/your/site/grav/user/plugins/broken-link-audit`.

### Manual Installation

To install this plugin, just download the zip version of this repository and unzip it under `/your/site/grav/user/plugins`. Then, rename the folder to `broken-link-audit`. You can find these files on [GitHub](https://github.com/jeremy-gonyea/grav-plugin-broken-link-audit) or via [GetGrav.org](http://getgrav.org/downloads/plugins#extras).

You should now have all the plugin files under

    /your/site/grav/user/plugins/broken-link-audit
	
> NOTE: This plugin is a modular component for Grav which requires [Grav](http://github.com/getgrav/grav) and the [Error](https://github.com/getgrav/grav-plugin-error) and [Problems](https://github.com/getgrav/grav-plugin-problems) to operate.

## Configuration

Before configuring this plugin, you should copy the `user/plugins/broken-link-audit/broken-link-audit.yaml` to `user/config/plugins/broken-link-audit.yaml` and only edit that copy.

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

- Anchor links within a markdown page are not checked.
- Links within twig are not checked.

## To Do

- Add Rendered content processing.
- Possibly leverage 404 log via logerrors plugin?
- Create new report based grouped not by pages, but on the broken referenced link
- CLI instructions
- Add auditing admin permissions.
- Wire up quick-tray-icon to perform audit.
- Update

## Thanks and Acknowledgements

I cribbed quite a bit from the TNTSearch plugin's scheduling and overall plugin structure.