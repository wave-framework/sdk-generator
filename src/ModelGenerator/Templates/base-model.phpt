<?php

/**
 * Model base class generated by the SDKGenerator\Generator.
 * Changes made to this file WILL BE OVERWRITTEN when the generator is run.
 * To add/override functionality, edit the child stub class.
 *
 */

namespace {{ namespace }}\Base;

use HellPizza\RPC\Response;
use HellPizza\StyxSDK\Client\Exception\InvalidInputException;

abstract class {{ class }} extends {{ base_model }} {

{% for operation in operations %}
{% set body_params = operation.method in ['post', 'put', 'patch'] or operation.paramaters.body is not empty %}
{% set query_params = operation.method in ['get', 'head', 'delete'] or operation.paramaters.query is not empty %}

    /**
{% for line in operation.comment|explode('\n') %}     * {{ line }}{{ '\n' }}{% if loop.last %}{{ '     *\n' }}{% endif %}{% endfor %}
{% for param in operation.parameters.path      %}     * @param {{ param.type }} ${{ param.name }}{{ '\n' }}{% endfor %}
{% if query_params                             %}     * @param mixed[] $query
{% for param in operation.parameters.query     %}     *        ['{{ param.name }}'] {{ param.type }} {% if param.required %}required{% endif %}{% endfor %}
     *
{% endif %}
{% if body_params                              %}     * @param mixed[] $body
{% for param in operation.parameters.body      %}     *        ['{{ param.name }}'] {{ param.type }} {% if param.required %}required{% endif %}{{ '\n' }}{% endfor %}
{% endif %}
{% if operation.parameters.query is not empty or operation.parameters.body is not empty %}
     *
{% endif %}
     *
     * @return Response|null
     */
	public static function {{ operation.function }}({% for param in operation.parameters.path %}${{ param.name }}, {% endfor %}{% if query_params %}array $query = array(){% if body_params %}, {% endif %}{% endif %}{% if body_params %}array $body = array(){% endif %}, $cache = false){

        $route = {% if operation.path_replacements is empty %}'{{ operation.path }}'{% else %}sprintf('{{ operation.path }}', {% for var,index in operation.path_replacements %}${{ var }}{% if not loop.last %}, {% endif %}{% endfor %}){% endif %};

        return self::_request('{{ meta['routing-key'] }}', '{{ operation.method }}', $route, {{ query_params ? '$query' : '[]' }}, {{ body_params ? '$body' : '[]' }}, $cache);
    }

{% endfor %}

}