# Getting Started With DynamicDataTableBundle

### Prerequisites

This bundle has been developped and tested in Symfony 3.4 and using PHP 7.1.33

### Installation

1. Add the bundle to your required composer libraries with the command :
    ```
    composer require amm/symfony-dynamic-datatable-bundle
    ```
2. Include the bundle into your AppKernel. Ex:
    ```php
    class AppKernel extends Kernel
    {
    public function registerBundles()
    {
        $bundles = [
            // ...
            new \AMM\SymfonyDynamicDataTableBundle\AMMSymfonyDynamicDataTableBundle(),
            // ...
        ];
        ... rest of your AppKernel
    ```
3. Load the assets into your web file with the command :
    ```
    symfony assets:install web --symlink
    ```

# Using DynamicDataTableBundle

### Make your Twig view containing your DataTable

First of all you need to construct your DataTable structure normally.

It also needs a specific id.

Ex:

```html

<table id="YourId_dt_table">
    <tfoot>
    <tr>
        <th>Title</th>
        <th>Author</th>
        <th>Date</th>
        <th>Published</th>
    </tr>
    </tfoot>
    <thead>
    <tr>
        <th>Title</th>
        <th>Author</th>
        <th>Date</th>
        <th>Published</th>
    </tr>
    </thead>
    <tbody></tbody>
</table>

```
		
It should include all the needed libraries such as :
- (Basic) Jquery, DataTable (JS) , DataTable (CSS)
- **(Needed) configDynamicDataTables.js**
		
You'll also need a script that:
- Initialise a variable with the Url for the controller that fetches the data
- Initialise the DataTable into a variable 
- Calls the config function, either the single or the multi searchBar one:
    - __For a single searchbar : configDataTableSingleSearch__
    - __For a multi searchbar : configDataTableMultiSearch__

Here is the needed parameters for these functions:
- __For both__ the single and the multi search you need to pass the table object created in your script.
- __For the multi search__ you also need to pass some optional parameters. Heres all the parameters possible:
    - Class  : will apply a class to all the inputs for css formating.

Exemple on how to use the parameters:
```javascript
configDataTableMultiSearch(table,[["class","yourCssClass"]]);
```
    

Exemple of how the whole script should look like:
```html
<script type="text/javascript" charset="utf8">
    $(document).ready(function () {
        var datatableurl = "{{ path('Your_datatable_fetch_data') }}";
    
        var table = $('#YourId_dt_table').DataTable({
            "columnDefs":[
    			{"name": "title", "targets": 0},        # Here set all the columns 
    			{"name": "author", "targets": 1},       # that are in the DataTable	
    			{"name": "date", "targets": 2},		# With an index that represents	
    			{"name": "published", "targets": 3}     # the column's position.
    			],                                     
    			"paging": true,
    			"info": true,
    			"searching": true,
    			"responsive": true,
    			"pageLength": 10,
    			"bProcessing": true,
    			"bServerSide": true,
    			"sAjaxSource": {
    				"url": datatableurl,
    				"type": "POST"
    			},
    			"fnServerData": fnDataTablesPipeline,
    			"order": [[0, 'asc']]
    		});
    		
	    configDataTableSingleSearch(table);		# Config function (Multi or Single Search)
	    
	});

</script>
```
				
### Make your view twig that applies your format
	
It is a simple view that goes through all the data applying your html/css format to each columns data.
		
Ex:
```twig
{% spaceless %}
    {% set output=[] %}

    {% for key, data in input %}

        {% set obj = [] %}

        {% for key, property in properties %}

            {% if property == 'title' %}		# Only need to adapt the if else
							# to the current DataTable's content.
                {% set obj = obj|merge([('
                                                # Html/Css formating can be applied
                    <h1 class="test">%s</h1>	# here , %s represents where the data
						# will appear.
                ')|format(data.title)
                ]) %}

            {% elseif property == 'author' %}
                {% set obj = obj|merge([('
                    %s
                ')|format(data.author)
                ]) %}

            {% elseif property == 'date' %}
                {% set obj = obj|merge([('
                        %s
                ')|format(data.date|date("Y-m-d H:i:s"))
                ]) %}

            {% elseif property == 'published' %}
                {% set obj = obj|merge([('
                    %s
                ')|format(data.published)
                ]) %}

            {% endif %}

        {% endfor %}

        {% set output = output|merge([obj]) %}

    {% endfor %}
    {{ output|json_encode|raw }}
{% endspaceless %}
```
			
### Make your controller
		
The only two controller functions that you need are :

- One to load your view.
- One that will fetch and return the data requested by the DataTable.
					
The last function needs to create a BuildDataService service __and set his parameters__.

Heres how your two functions should look like in the end :
```php
    public function indexAction()
    {
        return $this->render('TestBundle:Default:index.html.twig');
    }

    public function getTestTableAction(Request $request)
    {

        $serviceDT = $this->get("amm_symfony_dynamic_data_table.builddataservice");

        $serviceDT->set(Advert::class,Advert::DATATABLE_Edit);
	
	// ...
	
	Modify the query here
	
	// ...

        $returnResponse = new JsonResponse();

        $returnResponse->setJson($serviceDT->renderData($query));	# here the json of the response
									# is directly the return of 
        return $returnResponse;						# the service
    }
```

**Here's the parameters that are required by the service :**

- The class of the object you're using (in this case Advert::class)
- The path to the twig view that formats the data (the second view we created)
    For this twig view we recommend having a public variable in your Class that references the path
    Ex:
    ```php
        const DATATABLE_Edit = 'YourNameSpace:Advert/DATATABLE_TEMPLATES:dataFormating.html.twig';
    ```
You can send the response you get from the service back directly to the DataTable.

And that's pretty much all for the controller part and how to use the Bundle!

			
				
