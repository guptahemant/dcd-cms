# DCD Drupal Custom Theme

This is the custom Drupal theme named **DCD**, built with SCSS support and structured for modern frontend development.

---

## 🚀 Quick Start – Setup After Site Clone

### 1. navigate into the theme directory and install the required Node.js dependencies, then compile the SCSS files into CSS by running
```
cd dcd  
npm install  

# Compile SCSS to CSS once
npm run build

# (Optional) Keep watching for changes and auto-compile
npm run watch
```

* Note: * Make sure to commit the compiled assets on git.

### 2.Enable the theme in Drupal and set it as the default theme by running these commands from the root of your Drupal project
```
drush theme:enable dcd  
drush config:set system.theme default dcd  
drush cr
```
