<?php

namespace AlfieHD\MarkdownDocumentation;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:docs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate documentation for the core project library.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command to generate documentation for the core project library.
     *
     * @return void
     */
    public function handle()
    {
        // get files from app directory
        $files = Storage::disk('app')->allFiles();

        foreach($files as $file)
        {   
            try {
                // get class name using file
                $class_name = '\\App\\' . str_replace('.php', '', str_replace('/', '\\', $file));
                // get reflection class object
                $class = new ReflectionClass($class_name);
            } catch (\Exception $exception) {
                continue;
            }

            // get path to output markdown file to (follows namspace structure)
            $md_path = $this::getClassSlug($class->getName());

            // generate markdown for class
            $md = $this::generateMarkdown($class);

            // store markdown file in docs directory
            Storage::disk('docs')->put($md_path . '.md', $md);
        }

        // output success message
        $this->info('Class documentation has been generated!');
    }

    /**
     * Get important details about the given class.
     *
     * @param  \ReflectionClass $class
     *
     * @return array
     */
    protected function getClassDetails(ReflectionClass $class)
    {
        // get parent class, interfaces, and traits
        $extends = $class->getParentClass();
        $interfaces = $class->getInterfaces();
        $traits = $class->getTraits();

        // return array of details
        return [
            'name' => $class->getName(),
            'short_name' => $class->getShortName(),
            'namespace' => $class->getNamespaceName(),
            'methods' => $this::getMethodDetails($class),
            'properties' => $this::getPropertyDetails($class),
            'extends' => $extends ? $this::getClassLink($extends) : 'Nothing',
            'implements' => $interfaces ? $this::getClassArrayLinks($interfaces) : 'Nothing',
            'uses' => $traits ? $this::getClassArrayLinks($traits) : 'Nothing',
        ];
    }

    /**
     * Get method details for the given class.
     *
     * @param  \ReflectionClass $class
     *
     * @return array
     */
    protected function getMethodDetails(ReflectionClass $class)
    {
        // get methods for class
        $methods = $this::getClassMethods($class);

        // init class methods array to be returned
        $class_methods = [];
        foreach($methods as $method)
        {
            // init method prefix and method tip type (used for markdown)
            $method_prefix = '';
            $method_tip_type = 'tip';

            // set method type string
            switch($method->getModifiers()) {
                case $method::IS_PROTECTED:
                    $method_prefix = 'protected';
                    $method_tip_type = 'warning';
                    break;
                case $method::IS_PRIVATE:
                    $method_prefix = 'private';
                    $method_tip_type = 'warning';
                    break;
                case $method::IS_ABSTRACT:
                    $method_prefix = 'abstract';
                    $method_tip_type = 'warning';
                    break;
                case $method::IS_FINAL:
                    $method_prefix = 'final';
                    $method_tip_type = 'warning';
                    break;
                default:
                    $method_prefix = 'public';
                    break;
            }

            // if method is static, apply it to the string
            if ($method->isStatic())
            {
                $method_prefix .= ' static';
            }

            // get method doc comment
            $doc_comment = $method->getDocComment();
            // get method parameters
            $parameters = $method->getParameters();

            // get parameter details from doc comment using regex
            preg_match_all('/@param\s+(\S+)\s*\$([\w]+)\s*([^\*]+)*$/m', $doc_comment, $doc_comment_params);

            // set param types and descriptions in arrays
            $param_types = $doc_comment_params[1];
            $param_descriptions = $doc_comment_params[3];

            // init method params array
            $method_params = [
                'values' => [],
                'strings' => [],
            ];

            foreach($parameters as $parameter)
            {
                // get position for parameter (where to look in previously set array)
                $position = $parameter->getPosition();

                // init param details
                $param_type = 'mixed';
                $param_description = null;
                $param_default = null;

                // get param type
                if (isset($param_types) && !empty($param_types))
                {
                    if (isset($param_types[$position]) && !empty($param_types[$position]))
                    {
                        $param_type = $param_types[$position];
                    }
                }
                
                // get param description
                if (isset($param_descriptions) && !empty($param_descriptions))
                {
                    if (isset($param_descriptions[$position]) && !empty($param_descriptions[$position]))
                    {
                        $param_description = $param_descriptions[$position];
                    }
                }

                // get parameter name
                $param_name = $parameter->getName();
                // set parameter string representation
                $param_string = "{$param_type} \${$param_name}";

                // if parameter has default value
                if ($parameter->isOptional() && $parameter->isDefaultValueAvailable())
                {
                    // get default value type
                    $param_default = $parameter->getDefaultValue();
                    $param_default = $param_default ?: 'null';

                    // if type is array then display that in string format
                    if (is_array($param_default))
                    {
                        $param_default = "[]";
                    }

                    // append default to string
                    $param_string .= " = {$param_default}";
                }

                // set method parameter details
                $method_params['values'][] = [
                    'name' => $param_name,
                    'type' => $param_type,
                    'description' => $param_description,
                    'default' => $param_default,
                ];
                $method_params['strings'][] = $param_string;
            }
            
            // join parameter strings
            $method_params_string = implode(', ', $method_params['strings']);

            // init method doc comment based info
            $method_return_type = 'void';
            $method_description = null;

            // get return type and description from doc comment if one exists
            if ($doc_comment)
            {
                // get method return type
                preg_match('/@return\s(.*)$/m', $doc_comment, $method_return_type_data);
                if (isset($method_return_type_data[1]))
                {
                    $method_return_type = $method_return_type_data[1];
                }

                // get method description
                preg_match('/\*\s*(\w[^\*].+)$/m', $doc_comment, $method_description);
                if (isset($method_description[1]))
                {
                    $method_description = $method_description[1];
                }
            }

            // set class methods details
            $class_methods[] = [
                'name' => $method->name,
                'prefix' => $method_prefix,
                'tip_type' => $method_tip_type,
                'params' => empty($method_params['values']) ? null : $method_params['values'],
                'return_type' => $method_return_type,
                'definition' => "{$method_prefix} function {$method->name}( {$method_params_string} ) : {$method_return_type}",
                'description' => $method_description,
            ];
        }

        return $class_methods;
    }
    

    /**
     * Get property details for the given class.
     *
     * @param  \ReflectionClass $class
     *
     * @return array
     */
    protected function getPropertyDetails(ReflectionClass $class)
    {
        // get properties for class
        $properties = $this::getClassProperties($class);

        // init class properties array to be returned
        $class_properties = [];

        foreach($properties as $property)
        {
            // init property prefix and property tip type (used for markdown)
            $property_prefix = '';
            $property_tip_type = 'tip';

            // set property type string
            switch($property->getModifiers()) {
                case $property::IS_PROTECTED:
                    $property_prefix = 'protected';
                    $property_tip_type = 'warning';
                    break;
                case $property::IS_PRIVATE:
                    $property_prefix = 'private';
                    $property_tip_type = 'warning';
                    break;
                default:
                    $property_prefix = 'public';
                    break;
            }

            // if property is static, apply it to the string
            if ($property->isStatic())
            {
                $property_prefix .= ' static';
            }

            // get property doc comment
            $property_doc_comment = $property->getDocComment();
            $property_type = 'mixed';
            $property_description = null;

            // get property details from doc comment using regex
            preg_match('/@var\s+(.+)\s\$([\w]+)\s*([^\*]+)*$/m', $property_doc_comment, $var_doc_comment);
            
            // if doc comment exists then get var type and description
            if ($var_doc_comment)
            {
                $var_type = $var_doc_comment[1];
                $var_description = $var_doc_comment[3];

                if ($var_type)
                {
                    $property_type = $var_type;
                }

                if ($var_description)
                {
                    $property_description = $var_description;
                }
            }

            // set class properties in array
            $class_properties[] = [
                'name' => $property->name,
                'prefix' => $property_prefix,
                'tip_type' => $property_tip_type,
                'type' => $property_type,
                'definition' => "{$property_prefix} \${$property->name};",
                'description' => $property_description,
            ];
        }

        return $class_properties;
    }

    /**
     * Generate the markdown for a class.
     *
     * @param  \ReflectionClass $class
     *
     * @return string
     */
    protected function generateMarkdown(ReflectionClass $class)
    {
        // get class details
        $class_definitions = $this::getClassDetails($class);

        // init markdown with class short_name
        $md = "# {$class_definitions['short_name']}\n";

        // add name and class details to markdown
        $md .= "
        ## `{$class_definitions['name']}`
        
        |                |         |
        | -------------: | :------ |
        | **Extends**    | {$class_definitions['extends']}    |
        | **Implements** | {$class_definitions['implements']} |
        | **Uses**       | {$class_definitions['uses']}       |

        ### Methods
        ";

        // add methods to markdown
        if (!empty($class_definitions['methods']))
        {
            foreach($class_definitions['methods'] as $method)
            {
                $md .= "
                ::: {$method['tip_type']} {$method['name']}
                -----";

                if ($method['description'])
                {
                    $md .= "
                    {$method['description']}
                    ";
                }

                $md .= "
                ```php{4}
                {$method['definition']}
                ```
                ";

                if ($method['params'])
                {
                    $md .= "
                    | Parameter | Type(s)   | Description |
                    | --------- | :-------: | :----------- |
                    ";

                    foreach($method['params'] as $param)
                    {
                        $label_string = '';
                        if(isset($param['default']))
                        {
                            $label_string = '<Badge text="optional" type="warn"/>';
                        }

                        $md .= "| `\${$param['name']}`{$label_string} | `{$param['type']}` | {$param['description']} |\n";
                    }
                }

                $md .= ":::\n";
            }
        }
        // if not then add messsage to markdown
        else
        {
            $md .= "
            > There are no methods for this class.
            ";
        }

        // add properties title to markdown
        $md .= "
        ### Properties
        ";

        // add properties to markdown
        if (!empty($class_definitions['properties']))
        {
            foreach($class_definitions['properties'] as $property)
            {
                $md .= "
                ::: {$property['tip_type']} \${$property['name']}
                -----";

                if ($property['description'])
                {
                    $md .= "
                    {$property['description']}
                    ";
                }

                $md .= "
                ```php{4}
                {$property['definition']}
                ```
                ***Type***
                * `{$property['type']}`
                ";

                $md .= ":::";
            }
        }
        // if not then add messsage to markdown
        else
        {
            $md .= "
            > There are no properties for this class.
            ";
        }

        // remove any leading whitespace from every line to tidy up the markdown
        $md = preg_replace('/^( )+/m', '', $md);

        return $md;
    }

    /**
     * Get a slug for the given class.
     *
     * @param  string $class_name Name of the class
     * @param  boolean $is_internal Option to say whether the class is internal or external
     *
     * @return string
     */
    protected function getClassSlug($class_name, $is_internal = true)
    {
        // if internal then use kebab case for the slug
        if ($is_internal)
        {
            $parts = explode('\\', $class_name);
            $parts = array_map(function($part) {
                return Str::kebab($part, '-');
            }, $parts);
            $slug = implode('/', $parts);
        }
        // if external use the namespace as it is for the slug
        else
        {
            $slug = str_replace('\\', '/', $class_name);
        }

        return $slug;
    }

    /**
     * Get a markdown link for a given class.
     *
     * @param  \ReflectionClass $class
     *
     * @return string
     */
    protected function getClassLink(ReflectionClass $class)
    {
        // check if class is in the \App namespace or not
        $is_internal = explode('\\', $class->getName())[0];
        $is_internal = $is_internal === 'App';

        // get name and slug of class
        $name = $class->getName();
        $slug = $this::getClassSlug($name, $is_internal);

        // get laravel version for linking to API documentation
        $laravel_version = explode('.', app()::VERSION);
        array_pop($laravel_version);
        $laravel_version = implode('.', $laravel_version);

        // return correct link depending on internal or external reference
        return $is_internal ?
            "[${name}](/{$slug}.html)" :
            "[${name}](https://laravel.com/api/{$laravel_version}/{$slug}.html)";
    }

    /**
     * Get a comma seperated list of markdown links from a given list of classes.
     *
     * @param  array $classes List of classes to return links for.
     *
     * @return string
     */
    protected function getClassArrayLinks($classes)
    {
        return implode(', ', array_map(function($class) {
            return $this::getClassLink($class);
        }, $classes));
    }

    /**
     * Get all methods that the given class defines.
     *
     * @param  \ReflectionClass $class
     *
     * @return array
     */
    protected function getClassMethods(ReflectionClass $class)
    {
        // get provided class' methods
        $methods = [];
        $class_methods = $class->getMethods();
        foreach($class_methods as $class_method)
        {
            $methods[$class_method->getName()] = $class_method;
        }

        // get parent class
        $parent_methods = [];
        $parent = $class->getParentClass();
        if ($parent)
        {
            // get parent class' methods
            $parent_class_methods = $parent->getMethods();
            foreach($parent_class_methods as $class_method)
            {
                $parent_methods[$class_method->getName()] = $class_method;
            }

            // if there are parent class methods then remove parent class methods from list
            if (!empty($parent_methods))
            {
                $class_methods = array_diff(
                    array_keys($methods),
                    array_keys($parent_methods)
                );
                $class_methods = array_intersect_key($methods, array_flip($class_methods));
            }
        }

        $trait_methods = [];
        $traits = $class->getTraits();

        if ($traits)
        {
            // get trait methods
            foreach($traits as $trait)
            {
                $trait_class_methods = $trait->getMethods();
                foreach($trait_class_methods as $class_method)
                {
                    $trait_methods[$class_method->getName()] = $class_method;
                }
            }
    
            // if there are trait methods then remove trait methods from list
            if (!empty($trait_methods))
            {
                $class_methods = array_diff(
                    array_keys($class_methods),
                    array_keys($trait_methods)
                );
                $class_methods = array_intersect_key($methods, array_flip($class_methods));
            }
        }
        
        return $class_methods;
    }
    
    /**
     * Get all properties that the given class defines.
     *
     * @param  \ReflectionClass $class
     *
     * @return array
     */
    protected function getClassProperties(ReflectionClass $class)
    {
        // get provided class' properties
        $properties = [];
        $class_properties = $class->getProperties();
        foreach($class_properties as $class_property)
        {
            $properties[$class_property->getName()] = $class_property;
        }

        // get parent class
        $parent_properties = [];
        $parent = $class->getParentClass();
        if ($parent)
        {
            // get parent class' properties
            $parent_class_properties = $parent->getProperties();
            foreach($parent_class_properties as $class_property)
            {
                $parent_properties[$class_property->getName()] = $class_property;
            }

            // if there are parent class properties then remove parent class properties from list
            if (!empty($parent_properties))
            {
                $class_properties = array_diff(
                    array_keys($properties),
                    array_keys($parent_properties)
                );
                $class_properties = array_intersect_key($properties, array_flip($class_properties));
            }
        }

        $trait_properties = [];
        $traits = $class->getTraits();

        if ($traits)
        {
            // get trait properties
            foreach($traits as $trait)
            {
                $trait_class_properties = $trait->getProperties();
                foreach($trait_class_properties as $class_property)
                {
                    $trait_properties[$class_property->getName()] = $class_property;
                }
            }
    
            // if there are trait properties then remove trait properties from list
            if (!empty($trait_properties))
            {
                $class_properties = array_diff(
                    array_keys($class_properties),
                    array_keys($trait_properties)
                );
                $class_properties = array_intersect_key($properties, array_flip($class_properties));
            }
        }
        
        return $class_properties;
    }
}
