'use strict';

const { exec } = require('child_process');

// Do this as the first thing so that any code reading it knows the right env.
process.env.BABEL_ENV = 'production';
process.env.NODE_ENV = 'production';
process.env.GENERATE_SOURCEMAP = 'false';

// Makes the script crash on unhandled rejections instead of silently
// ignoring them. In the future, promise rejections that are not handled will
// terminate the Node.js process with a non-zero exit code.
process.on('unhandledRejection', err => {
  throw err;
});

// Ensure environment variables are read.
require('../config/env');


const webpack = require('webpack');
const configFactory = require('../config/webpack.config');

// Generate configuration
const config = configFactory('production');
const compiler = webpack(config);

const watching = compiler.watch({
  // Example watchOptions
  aggregateTimeout: 300,
  poll: undefined,
  "infoVerbosity": "verbose"
}, (err, stats) => { // Stats Object
  // Print watch/build result here...
  // console.log(stats);
  if (err || stats.compilation.errors.length) {
    if (stats) {
      console.log(stats.compilation.errors);
    } else {
      console.log(err);
    }
    
    notify("fail");
    return;
  }

  console.log("Rebuilt ", new Date().getTime());
  notify("pass");
});

const notify = (type) => {
  exec(`npx osx-notifier --type ${type} --title "Customizer build watch" --message "Build done: ${type}"`, (err, stdout, stderr) => {
    if (err) {
      console.error(err);
      return;
    }
    console.log(stdout);
  });
};
