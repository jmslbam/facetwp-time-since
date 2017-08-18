<?php
/*
Plugin Name: FacetWP - Time Since
Description: "Time Since" facet
Version: 1.3.3
Author: FacetWP, LLC
Author URI: https://facetwp.com/
GitHub URI: facetwp/facetwp-time-since
*/

defined( 'ABSPATH' ) or exit;


/**
 * FacetWP registration hook
 */
function fwp_time_since_facet( $facet_types ) {
    $facet_types['time_since'] = new FacetWP_Facet_Time_Since();
    return $facet_types;
}
add_filter( 'facetwp_facet_types', 'fwp_time_since_facet' );


/**
 * Time Since facet class
 */
class FacetWP_Facet_Time_Since
{

    function __construct() {
        $this->label = __( 'Time Since', 'fwp' );
    }


    /**
     * Parse the multi-line options string
     */
    function parse_choices( $choices ) {
        $choices = explode( "\n", $choices );
        foreach ( $choices as $key => $choice ) {
            $temp = array_map( 'trim', explode( '|', $choice ) );
            $choices[ $key ] = array(
                'label' => $temp[0],
                'format' => $temp[1],
                'seconds' => strtotime( $temp[1] ),
                'counter' => 0,
            );
        }

        return $choices;
    }


    /**
     * Load the available choices
     */
    function load_values( $params ) {
        global $wpdb;

        $facet = $params['facet'];
        $where_clause = $params['where_clause'];

        $sql = "
        SELECT f.facet_display_value
        FROM {$wpdb->prefix}facetwp_index f
        WHERE f.facet_name = '{$facet['name']}' $where_clause";
        $results = $wpdb->get_results( $sql, ARRAY_A );

        // Parse facet choices
        $choices = $this->parse_choices( $facet['choices'] );

        // Loop through the results
        foreach ( $results as $result ) {
            $post_time = (int) strtotime( $result['facet_display_value'] );
            foreach ( $choices as $key => $choice ) {
                $choice_time = $choice['seconds'];

                // last week, etc.
                if ( $choice_time < time() && $post_time >= $choice_time ) {
                    $choices[ $key ]['counter']++;
                }
                // next week, etc.
                elseif ( $choice_time > time() && $post_time <= $choice_time ) {
                    $choices[ $key ]['counter']++;
                }
            }
        }

        // Return an associative array
        $output = array();
        foreach ( $choices as $choice ) {
            if ( 0 < $choice['counter'] ) {
                $output[] = array(
                    'facet_display_value' => $choice['label'],
                    'counter' => $choice['counter'],
                );
            }
        }

        return $output;
    }


    /**
     * Generate the facet HTML
     */
    function render( $params ) {

        $output = '';
        $facet = $params['facet'];
        $values = (array) $params['values'];
        $selected_values = (array) $params['selected_values'];

        $is_empty = empty( $selected_values ) ? ' checked' : '';
        $output .= '<div class="facetwp-radio' . $is_empty  . '" data-value="">' . __( 'Any', 'fwp' ) . '</div>';

        foreach ( $values as $result ) {
            $display_value = esc_html( $result['facet_display_value'] );
            $safe_value = FWP()->helper->safe_value( $display_value );
            $selected = in_array( $safe_value, $selected_values ) ? ' checked' : '';
            $display_value .= " <span class='counts'>(" . $result['counter'] . ")</span>";
            $output .= '<div class="facetwp-radio' . $selected . '" data-value="' . esc_attr( $safe_value ) . '">' . $display_value . '</div>';
        }

        return $output;
    }


    /**
     * Filter the query based on selected values
     */
    function filter_posts( $params ) {
        global $wpdb;

        $facet = $params['facet'];
        $selected_values = $params['selected_values'];
        $selected_values = is_array( $selected_values ) ? $selected_values[0] : $selected_values;

        $choices = $this->parse_choices( $facet['choices'] );
        foreach ( $choices as $key => $data ) {
            $safe_value = FWP()->helper->safe_value( $data['label'] );
            if ( $safe_value === $selected_values ) {
                $selected_values = date( 'Y-m-d H:i:s', (int) $data['seconds'] );
                break;
            }
        }

        $sql = "
        SELECT DISTINCT post_id FROM {$wpdb->prefix}facetwp_index
        WHERE facet_name = '{$facet['name']}' AND facet_value >= '$selected_values'";
        return $wpdb->get_col( $sql );
    }


    /**
     * Output any admin scripts
     */
    function admin_scripts() {
?>
<script>
(function($) {
    $(function() {
        wp.hooks.addAction('facetwp/load/time_since', function($this, obj) {
            $this.find('.facet-source').val(obj.source);
            $this.find('.facet-choices').val(obj.choices);
        });
    
        wp.hooks.addFilter('facetwp/save/time_since', function(obj, $this) {
            obj['source'] = $this.find('.facet-source').val();
            obj['choices'] = $this.find('.facet-choices').val();
            return obj;
        });
    });
})(jQuery);
</script>
<?php
    }


    /**
     * Output any front-end scripts
     */
    function front_scripts() {
?>

<script>
(function($) {
    wp.hooks.addAction('facetwp/refresh/time_since', function($this, facet_name) {
        var selected_values = [];
        $this.find('.facetwp-radio.checked').each(function() {
            var val = $(this).attr('data-value');
            if ('' != val) {
                selected_values.push(val);
            }
        });
        FWP.facets[facet_name] = selected_values;
    });

    wp.hooks.addFilter('facetwp/selections/time_since', function(output, params) {
        var labels = [];
        $.each(params.selected_values, function(idx, val) {
            var label = params.el.find('.facetwp-radio[data-value="' + val + '"]').clone();
            label.find('.counts').remove();
            labels.push(label.text());
        });
        return labels.join(' / ');
    });

    wp.hooks.addAction('facetwp/ready', function() {
        $(document).on('click', '.facetwp-radio', function() {
            var $facet = $(this).closest('.facetwp-facet');
            $facet.find('.facetwp-radio').removeClass('checked');
            $(this).addClass('checked');
            if ('' != $(this).attr('data-value')) {
                FWP.static_facet = $facet.attr('data-name');
            }
            FWP.autoload();
        });
    });
})(jQuery);
</script>
<?php
    }


    /**
     * Output admin settings HTML
     */
    function settings_html() {
?>
        <tr>
            <td>
                <?php _e('Choices', 'fwp'); ?>:
                <div class="facetwp-tooltip">
                    <span class="icon-question">?</span>
                    <div class="facetwp-tooltip-content"><?php _e( 'Enter the available choices (one per line)', 'fwp' ); ?></div>
                </div>
            </td>
            <td><textarea class="facet-choices"></textarea></td>
        </tr>
<?php
    }
}
