const fs = require('fs');
const path = require('path');

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

fs.rmSync(bowerComponents, { recursive: true, force: true });

copyDirectory(fromPackage('bootstrap', 'dist'), toAsset('bootstrap', 'dist'));
copyDirectory(fromPackage('font-awesome', 'css'), toAsset('font-awesome', 'css'));
copyDirectory(fromPackage('font-awesome', 'fonts'), toAsset('font-awesome', 'fonts'));
copyDirectory(fromPackage('ionicons', 'css'), toAsset('Ionicons', 'css'));
copyDirectory(fromPackage('ionicons', 'fonts'), toAsset('Ionicons', 'fonts'));
copyDirectory(fromPackage('jquery', 'dist'), toAsset('jquery', 'dist'));
copyFile(toAsset('jquery', 'dist', 'jquery.min.js'), toAsset('jquery', 'dist', 'jquery-3.4.1.min.js'));
copyDirectory(fromPackage('datatables.net', 'js'), toAsset('datatables.net', 'js'));
copyDirectory(fromPackage('datatables.net-bs', 'css'), toAsset('datatables.net-bs', 'css'));
copyDirectory(fromPackage('datatables.net-bs', 'js'), toAsset('datatables.net-bs', 'js'));
copyDirectory(fromPackage('jquery-ui-dist'), toAsset('jquery-ui-1.12.1'));
copyFile(fromPackage('moment', 'min', 'moment.min.js'), toAsset('moment', 'moment.min.js'));
copyFile(fromPackage('chart.js', 'dist', 'Chart.min.js'), toAsset('chart.js', 'Chart.min.js'));
copyFile(fromPackage('chart.js', 'dist', 'Chart.min.css'), toAsset('chart.js', 'Chart.min.css'));
copyDirectory(fromPackage('select2', 'dist'), toAsset('select2', 'dist'));
copyDirectory(fromPackage('bootstrap-datepicker', 'dist'), toAsset('bootstrap-datepicker', 'dist'));
copyDirectory(fromPackage('ckeditor'), toAsset('ckeditor'));

console.log('Frontend assets restored in assets/bower_components.');