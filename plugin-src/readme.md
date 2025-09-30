# Bynder WordPress Plugin

The Bynder WordPress plugin was built as a Gutenberg Block, that allows to add assets from Bynder to WordPress using the Compact View.

# Current status

The latest version of this plugin is **5.5.6** and requires at least **WordPress 5.9** and it was tested up to **WordPress 6.6.1**.

## Implemented features

- Bynder Asset Block
  - Allows users to add an asset (document, image, video) from their Bynder account to their WordPress pages and posts.
- Bynder Gallery Block
  - Allows users to create a gallery with images from their Bynder account on their WordPress pages and posts.
- Asset tracking
  - Cron job that syncs (once per hour) the Bynder assets used in WordPress back to Bynder. This allows users to keep track of those assets through their Bynder portal.

# How does it work

This project was bootstrapped with [WordPress Create Block](https://github.com/WordPress/gutenberg/blob/1f92999896beb98a572f46722e35b31b1de8d547/packages/create-block/README.md).

Below you will find some information on how to run scripts.

> You can find the more information [here](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-create-block/).

## 👉 `npm start`

- Use to compile and run the block in development mode.
- Watches for any changes and reports back any errors in your code.

## 👉 `npm run build`

- Use to build production code for your block inside `build` folder.
- Runs once and reports back the gzip file sizes of the produced code.

## Local Environment Development With Docker

Instructions based off of https://bynder.atlassian.net/wiki/spaces/BE/pages/3890774069/Setting+Up+Local+Development+Environment

1. From root directory run the following command: `docker-compose up` to start local WordPress environment.
   This will start service at `localhost:7777`. Containers that start up include MySQL, adminer, and WordPress
2. Setup a WordPress account and login at `http://localhost:7777/wp-admin`.
3. On the left navigate to `Plugins` and then to `Installed Plugins`. Activate the `Bynder` plugin.
4. On the left navigate to `Settings` and then to `Bynder` and configure plugin.
5. From the root directory run the following commands:

   `npm install`
   `npm run start`

6. Navigate back to WordPress running locally and to `Posts`. Edit or create a new post. Click the `+` button and
   then `Bynder Asset`. This will display the option to click `Open Compact View`.
7. Follow flow for UCV (Bynder or Webdam portal). Select assets to use within WordPress block.

## Plugin Structure

Reference: https://bynder.atlassian.net/wiki/spaces/BE/pages/3891036299/Overview+of+the+Plugin+Structure

---
