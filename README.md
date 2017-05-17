Sylius ElasticSearchBundle
==========================
Elastic search for Sylius.
[![Build status on Linux](https://img.shields.io/travis/Lakion/SyliusElasticSearchBundle/master.svg)](http://travis-ci.org/Lakion/SyliusELasticSearchBundle)

## Usage

1. Install it:

    ```bash
    $ composer require lakion/sylius-elastic-search-bundle
    ```
2. Install elastic search server, 5.x series required:

    MacOS
    
    ```bash
    $ brew install elasticsearch
    ```
    
    Ubuntu/Debian/etc
    ```bash
    $ apt-get install elasticsearch
    ```

3. Run elastic search server:

    ```bash
    $ elasticsearch
    ```

4. Add this bundle to `AppKernel.php`:

    ```php
    new \FOS\ElasticaBundle\FOSElasticaBundle(),
    new \Lakion\SyliusElasticSearchBundle\LakionSyliusElasticSearchBundle(),
    ```

5. Create/Setup database if required:

    ```bash
    $ bin/console do:da:cr
    $ bin/console do:sch:cr
    $ bin/console syl:fix:lo
    ```

6. Import config file in `app/config/config.yml` for default filter set configuration:
   
       ```yaml
       imports:
          - { resource: "@LakionSyliusElasticSearchBundle/Resources/config/app/config.yml" }
       ```

7. Import config file in `app/config/config.yml` for default filter set configuration:

    Populate your elastic search server with command or your custom code:

    ```bash
    $ bin/console fos:elastic:pop
    ```

8. Import routing files in `app/config/routing.yml`:

    ```yaml
    sylius_search:
        resource: "@LakionSyliusElasticSearchBundle/Resources/config/routing.yml"
    ```

8. Configuration reference:

    ```yaml
    lakion_sylius_elastic_search:
        filter_sets:
            mugs:
                filters:
                    product_options:
                        type: option
                        options:
                            code: mug_type
                    product_price:
                        type: price
    ```
