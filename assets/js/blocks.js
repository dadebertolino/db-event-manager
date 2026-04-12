/* DB Event Manager — Gutenberg Blocks */
(function(wp) {
    var el = wp.element.createElement;
    var registerBlockType = wp.blocks.registerBlockType;
    var SelectControl = wp.components.SelectControl;
    var RangeControl = wp.components.RangeControl;
    var ToggleControl = wp.components.ToggleControl;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody = wp.components.PanelBody;
    var ServerSideRender = wp.serverSideRender || wp.components.ServerSideRender;

    /* Blocco: Evento singolo */
    registerBlockType('dbem/event', {
        title: 'Evento (DB Event Manager)',
        icon: 'calendar-alt',
        category: 'widgets',
        attributes: {
            eventId: { type: 'number', default: 0 }
        },
        edit: function(props) {
            var eventId = props.attributes.eventId;
            var options = (window.dbemBlocks && window.dbemBlocks.events) || [{ label: '— Nessun evento —', value: 0 }];

            return el('div', {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Impostazioni' },
                        el(SelectControl, {
                            label: 'Seleziona evento',
                            value: eventId,
                            options: options,
                            onChange: function(val) { props.setAttributes({ eventId: parseInt(val) }); }
                        })
                    )
                ),
                eventId > 0
                    ? el(ServerSideRender, { block: 'dbem/event', attributes: props.attributes })
                    : el('div', { style: { padding: '20px', background: '#f0f0f0', borderRadius: '8px', textAlign: 'center' } },
                        el('p', {}, '📅 Seleziona un evento dalla barra laterale'),
                        el(SelectControl, {
                            value: eventId,
                            options: options,
                            onChange: function(val) { props.setAttributes({ eventId: parseInt(val) }); }
                        })
                    )
            );
        },
        save: function() { return null; } // Server-side rendering
    });

    /* Blocco: Lista eventi */
    registerBlockType('dbem/events-list', {
        title: 'Lista Eventi (DB Event Manager)',
        icon: 'list-view',
        category: 'widgets',
        attributes: {
            showPast: { type: 'boolean', default: false },
            limit: { type: 'number', default: 10 }
        },
        edit: function(props) {
            return el('div', {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Impostazioni' },
                        el(ToggleControl, {
                            label: 'Mostra eventi passati',
                            checked: props.attributes.showPast,
                            onChange: function(val) { props.setAttributes({ showPast: val }); }
                        }),
                        el(RangeControl, {
                            label: 'Numero massimo eventi',
                            value: props.attributes.limit,
                            min: 1, max: 50,
                            onChange: function(val) { props.setAttributes({ limit: val }); }
                        })
                    )
                ),
                el(ServerSideRender, { block: 'dbem/events-list', attributes: props.attributes })
            );
        },
        save: function() { return null; }
    });

})(window.wp);
