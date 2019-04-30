# Markdown Documentation
Generate documentation for your core Laravel project library in markdown format.

> This is a basic project, and does not produce full-blown documentation for every part of the application. Only the `/app` directory. This only creates documentation for:
> * Methods which are written in the class
> * Properties written in the class
> * References to:
>	* Parent class
>	* Interfaces
>	* Traits

## Install

To install just run:
```
composer require alfiehd/markdown-documentation --dev
```

## Setup
### Git
Your markdown files will appear in `/docs` in the root of your project directory. So we will first need to add `/docs` to the `.gitignore` file in the root of our project.

### Command
Next we need to add `\AlfieHD\MarkdownDocumentation\GenerateCommand::class` to the `$commands` array in our `app/Console/Kernel.php` file. It should then look something like the following:
```
protected $commands = [
    \AlfieHD\MarkdownDocumentation\GenerateCommand::class,
];
```

### Storage
Finally we need to create two entries in our `config/filesystems.php` file in order to read files from the `/app` directory and save them to the `/docs` directory. Simply add the following to `'disks' => [ ... ]`:
```
'app' => [
    'driver' => 'local',
    'root' => app_path(),
],

'docs' => [
    'driver' => 'local',
    'root' => base_path('docs'),
],
```

## How to use
Simply run the command:
```
php artisan make:docs
```
and all of your markdown files will appear in `/docs` in your project directory.

## That's it!
You are done. Now you can use your new markdown documentation with something like VuePress in order to produce beautiful documentation for your project.
