/*
 * Data Selector [VERSION]
 * [DATE]
 * Corey Hart http://www.codenothing.com
 */
(function( $, undefined ){

        // Globals
        var name, value, condition, original, eqIndex, cache = {}, special = {},
                rSpecial = /\[(.*?)\]$/,
                BasicConditions = {
                        '$': true,
                        '!': true,
                        '^': true,
                        '*': true,
                        '<': true,
                        '>': true,
                        '~': true
                };

        function parseQuery( query ) {
                original = query;

                if ( cache[ original ] ) {
                        name = cache[ original ].name;
                        value = cache[ original ].value;
                        condition = cache[ original ].condition;
                        eqIndex = cache[ original ].eqIndex;
                        return true;
                }

                // Find the first instance of equal sign for name=val operations
                eqIndex = query.indexOf( '=' );
                if ( eqIndex > -1 ) {
                        name = query.substr( 0, eqIndex );
                        value = query.substr( eqIndex + 1 ) || null;
                } else {
                        name = query;
                        value = null;
                }

                // Store condition (not required) for comparison
                condition = name.charAt( name.length - 1 );

                if ( BasicConditions[ condition ] === true ) {
                        name = name.substr( 0, name.length - 1 );
                }
                else if ( condition === ']' ) {
                        condition = rSpecial.exec( name )[ 1 ];
                        name = name.replace( rSpecial, '' );
                }

                // If >=, <=, or !! is passed, add to condition
                if ( value && ( condition === '<' || condition === '>' ) && value.charAt(0) === '=' ) {
                        value = value.substr( 1 );
                        condition = condition + '=';
                }
                // If regex condition passed, store regex into the value var
                else if ( condition === '~' ) {
                        value = new RegExp(
                                value.substr( 1, value.lastIndexOf('/') - 1 ), 
                                value.split('/').pop()
                        );
                }
                else if ( value && value.substr( 0, 2 ) === '==' ) {
                        condition = '===';
                        value = value.substr( 2 );
                }

                // Expand name to allow for multiple levels
                name = name.split('.');

                // Cache Results
                cache[ original ] = {
                        name: name,
                        value: value,
                        condition: condition,
                        eqIndex: eqIndex
                };
        }

        $.expr[':'].data = function( elem, index, params, group ) {
                if ( elem === undefined || ! params[3] || params[3] == '' ) {
                        return false;
                }
                else if ( original !== params[3] ) {
                        parseQuery( params[3] );
                }

                // Grab bottom most level data
                for ( var i = -1, l = name.length, data; ++i < l; ) {
                        if ( ( data = data === undefined ? $.data( elem, name[i] ) : data[ name[i] ] ) === undefined || data === null ) {
                                return false;
                        }
                }

                // No comparison passed, just looking for existence (which was found at this point)
                if ( eqIndex === -1 ) {
                        return true;
                }

                switch ( condition ) {
                        // Not equal to
                        case '!': return data.toString() !== value;
                        // Starts with
                        case '^': return data.toString().indexOf( value ) === 0;
                        // Ends with
                        case '$': return data.toString().substr( data.length - value.length ) === value;
                        // Contains
                        case '*': return data.toString().indexOf( value ) !== -1;
                        // Greater Than (or equal to)
                        case '>': return data > value;
                        case '>=': return data >= value;
                        // Less Than (or equal to)
                        case '<': return data < value;
                        case '<=': return data <= value;
                        // Boolean Check
                        case '===': return data === ( value === 'false' ? false : true );
                        // Regex Matching
                        case '~': return value.test( data.toString() );
                        // Defaults to either special user defined function, or simple '=' comparison
                        default: return special[ condition ] ? 
                                special[ condition ].call( elem, data, value, index, params, group ) :
                                ( data !== undefined && data !== null && data.toString() === value );
                }
        };

        // Give developers ability to attach their own special data comparison function
        $.dataSelector = function( o, fn ) {
                if ( $.isFunction( fn ) ) {
                        special[ o ] = fn;
                } else {
                        $.extend( special, o || {} );
                }
        };

})( jQuery );
