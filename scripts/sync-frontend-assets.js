const fs = require('fs');
const path = require('path');
const sass = require('sass');

const root = path.resolve(__dirname, '..');
const nodeModules = path.join(root, 'node_modules');
const bowerComponents = path.join(root, 'assets', 'bower_components');
const pluginsDir = path.join(root, 'assets', 'plugins');

function fromPackage(packageName, ...segments) {
  return path.join(nodeModules, packageName, ...segments);
}

function toAsset(...segments) {
  return path.join(bowerComponents, ...segments);
}

function toPlugin(...segments) {
  return path.join(pluginsDir, ...segments);
}

function copyDirectory(source, destination) {
  if (!fs.existsSync(source)) {
    throw new Error(`Missing package asset source: ${source}`);
  }

  fs.rmSync(destination, { recursive: true, force: true });
  fs.mkdirSync(path.dirname(destination), { recursive: true });
  fs.cpSync(source, destination, { recursive: true });
}

function copyFile(source, destination) {
  if (!fs.existsSync(source)) {
    throw new Error(`Missing package asset source: ${source}`);
  }

  fs.mkdirSync(path.dirname(destination), { recursive: true });
  fs.copyFileSync(source, destination);
}

function writeFile(destination, content) {
  fs.mkdirSync(path.dirname(destination), { recursive: true });
  fs.writeFileSync(destination, content);
}

function chownTree(target, uid, gid) {
  if (!fs.existsSync(target)) {
    return;
  }

  const stats = fs.lstatSync(target);
  if (stats.isDirectory()) {
    for (const entry of fs.readdirSync(target)) {
      chownTree(path.join(target, entry), uid, gid);
    }
  }

  if (typeof fs.lchownSync === 'function') {
    fs.lchownSync(target, uid, gid);
  } else {
    fs.chownSync(target, uid, gid);
  }
}

function restoreWorkspaceOwnership(...targets) {
  if (typeof process.getuid !== 'function' || process.getuid() !== 0) {
    return;
  }

  const owner = fs.statSync(root);
  for (const target of targets) {
    chownTree(target, owner.uid, owner.gid);
  }
}

function restoreBootstrap3Assets() {
  const bootstrapRoot = fromPackage('bootstrap-sass', 'assets');
  const stylesheets = path.join(bootstrapRoot, 'stylesheets');
  const bootstrapDist = toAsset('bootstrap', 'dist');
  const bootstrapScss = '@import \"bootstrap\";\n';

  fs.rmSync(bootstrapDist, { recursive: true, force: true });

  copyFile(path.join(bootstrapRoot, 'javascripts', 'bootstrap.js'), path.join(bootstrapDist, 'js', 'bootstrap.js'));
  copyFile(path.join(bootstrapRoot, 'javascripts', 'bootstrap.min.js'), path.join(bootstrapDist, 'js', 'bootstrap.min.js'));
  copyDirectory(path.join(bootstrapRoot, 'fonts'), path.join(bootstrapDist, 'fonts'));

  writeFile(
    path.join(bootstrapDist, 'css', 'bootstrap.css'),
    sass.compileString(bootstrapScss, { loadPaths: [stylesheets], style: 'expanded' }).css
  );
  writeFile(
    path.join(bootstrapDist, 'css', 'bootstrap.min.css'),
    sass.compileString(bootstrapScss, { loadPaths: [stylesheets], style: 'compressed' }).css
  );
}

fs.rmSync(bowerComponents, { recursive: true, force: true });
for (const plugin of ['alertify', 'animate', 'dropzone', 'toastr']) {
  fs.rmSync(toPlugin(plugin), { recursive: true, force: true });
}

restoreBootstrap3Assets();
copyDirectory(fromPackage('font-awesome', 'css'), toAsset('font-awesome', 'css'));
copyDirectory(fromPackage('font-awesome', 'fonts'), toAsset('font-awesome', 'fonts'));
copyDirectory(fromPackage('ionicons', 'css'), toAsset('Ionicons', 'css'));
copyDirectory(fromPackage('ionicons', 'fonts'), toAsset('Ionicons', 'fonts'));
copyDirectory(fromPackage('jquery', 'dist'), toAsset('jquery', 'dist'));
copyDirectory(fromPackage('datatables.net', 'js'), toAsset('datatables.net', 'js'));
copyDirectory(fromPackage('datatables.net-bs', 'css'), toAsset('datatables.net-bs', 'css'));
copyDirectory(fromPackage('datatables.net-bs', 'images'), toAsset('datatables.net-bs', 'images'));
copyDirectory(fromPackage('datatables.net-bs', 'js'), toAsset('datatables.net-bs', 'js'));
copyDirectory(fromPackage('jquery-ui-dist'), toAsset('jquery-ui'));
copyFile(fromPackage('moment', 'min', 'moment.min.js'), toAsset('moment', 'moment.min.js'));
copyFile(fromPackage('chart.js', 'dist', 'Chart.min.js'), toAsset('chart.js', 'Chart.min.js'));
copyFile(fromPackage('chart.js', 'dist', 'Chart.min.css'), toAsset('chart.js', 'Chart.min.css'));
copyDirectory(fromPackage('select2', 'dist'), toAsset('select2', 'dist'));
copyDirectory(fromPackage('bootstrap-datepicker', 'dist'), toAsset('bootstrap-datepicker', 'dist'));
copyDirectory(fromPackage('ckeditor4'), toAsset('ckeditor'));

// assets/plugins: legacy AdminLTE plugins that are still referenced by the
// views. Only the files the views load are materialized, reshaped into the
// historical paths so the <link>/<script> tags do not need to change.
copyFile(fromPackage('alertifyjs', 'build', 'alertify.min.js'), toPlugin('alertify', 'alertify.min.js'));
copyFile(fromPackage('alertifyjs', 'build', 'css', 'alertify.min.css'), toPlugin('alertify', 'css', 'alertify.min.css'));
copyFile(fromPackage('alertifyjs', 'build', 'css', 'themes', 'bootstrap.min.css'), toPlugin('alertify', 'css', 'themes', 'bootstrap.min.css'));
copyFile(fromPackage('animate.css', 'animate.min.css'), toPlugin('animate', 'animate.min.css'));
copyFile(fromPackage('dropzone', 'dist', 'min', 'dropzone.min.js'), toPlugin('dropzone', 'dropzone.js'));
copyFile(fromPackage('dropzone', 'dist', 'min', 'dropzone.min.css'), toPlugin('dropzone', 'dropzone.css'));
copyFile(fromPackage('toastr', 'build', 'toastr.min.js'), toPlugin('toastr', 'build', 'toastr.min.js'));
copyFile(fromPackage('toastr', 'build', 'toastr.min.css'), toPlugin('toastr', 'build', 'toastr.min.css'));

restoreWorkspaceOwnership(nodeModules, bowerComponents, pluginsDir);

console.log('Frontend assets restored in assets/bower_components and assets/plugins.');
