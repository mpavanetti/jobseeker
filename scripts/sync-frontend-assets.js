const fs = require('fs');
const path = require('path');
const sass = require('sass');

const root = path.resolve(__dirname, '..');
const nodeModules = path.join(root, 'node_modules');
const bowerComponents = path.join(root, 'assets', 'bower_components');

function fromPackage(packageName, ...segments) {
  return path.join(nodeModules, packageName, ...segments);
}

function toAsset(...segments) {
  return path.join(bowerComponents, ...segments);
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

restoreBootstrap3Assets();
copyDirectory(fromPackage('font-awesome', 'css'), toAsset('font-awesome', 'css'));
copyDirectory(fromPackage('font-awesome', 'fonts'), toAsset('font-awesome', 'fonts'));
copyDirectory(fromPackage('ionicons', 'css'), toAsset('Ionicons', 'css'));
copyDirectory(fromPackage('ionicons', 'fonts'), toAsset('Ionicons', 'fonts'));
copyDirectory(fromPackage('jquery', 'dist'), toAsset('jquery', 'dist'));
copyFile(toAsset('jquery', 'dist', 'jquery.min.js'), toAsset('jquery', 'dist', 'jquery-3.4.1.min.js'));
copyDirectory(fromPackage('datatables.net', 'js'), toAsset('datatables.net', 'js'));
copyDirectory(fromPackage('datatables.net-bs', 'css'), toAsset('datatables.net-bs', 'css'));
copyDirectory(fromPackage('datatables.net-bs', 'images'), toAsset('datatables.net-bs', 'images'));
copyDirectory(fromPackage('datatables.net-bs', 'js'), toAsset('datatables.net-bs', 'js'));
copyDirectory(fromPackage('jquery-ui-dist'), toAsset('jquery-ui-1.12.1'));
copyFile(fromPackage('moment', 'min', 'moment.min.js'), toAsset('moment', 'moment.min.js'));
copyFile(fromPackage('chart.js', 'dist', 'Chart.min.js'), toAsset('chart.js', 'Chart.min.js'));
copyFile(fromPackage('chart.js', 'dist', 'Chart.min.css'), toAsset('chart.js', 'Chart.min.css'));
copyDirectory(fromPackage('select2', 'dist'), toAsset('select2', 'dist'));
copyDirectory(fromPackage('bootstrap-datepicker', 'dist'), toAsset('bootstrap-datepicker', 'dist'));
copyDirectory(fromPackage('ckeditor'), toAsset('ckeditor'));

restoreWorkspaceOwnership(nodeModules, bowerComponents);

console.log('Frontend assets restored in assets/bower_components.');
