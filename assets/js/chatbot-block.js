( function ( blocks, element ) {
    var el = element.createElement;

    blocks.registerBlockType( 'chatbot/popup', {
        title: 'Chatbot Popup',
        icon: 'format-chat',
        category: 'widgets',
        description: 'Floating chatbot popup with round icon.',
        edit: function () {
            return el(
                'div',
                {
                    style: {
                        padding: '16px',
                        border: '1px dashed #667eea',
                        borderRadius: '8px',
                        background: '#f8f9ff'
                    }
                },
                'Chatbot Popup block: chat icon and popup will render on the frontend.'
            );
        },
        save: function () {
            return null;
        }
    } );
} )( window.wp.blocks, window.wp.element );
