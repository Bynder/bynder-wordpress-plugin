# Bynder WordPress Plugin

Official distribution channel for the Bynder WordPress Connector

## WordPress Requirements

There are currently two versions of the plugin in order to support WordPress 5.8 and earlier. The plugin files can be found in the folders named with the WordPress versions they currently support. In each folder, you will find a ZIP for the plugin and a ZIP that contains the source code if you want to make further customizations to the plugin.  

_The plugin for Wordpress 5.8 and earlier will not be actively maintained or developed on._ 

1. WordPress-5.9 (Requires WordPress 5.9)

2. WordPress-5.8-legacy (Requires WordPress 5.0 to 5.8)

# How Does it Work

This project was bootstrapped with [Wordpress Create Block](https://github.com/WordPress/gutenberg/blob/1f92999896beb98a572f46722e35b31b1de8d547/packages/create-block/README.md).

Below you will find some information on how to run scripts.

> You can find the more information [here](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-create-block/).

## 👉 `npm start`

- Use to compile and run the block in development mode.
- Watches for any changes and reports back any errors in your code.

## 👉 `npm run build`

- Use to build production code for your block inside `build` folder.
- Runs once and reports back the gzip file sizes of the produced code.
