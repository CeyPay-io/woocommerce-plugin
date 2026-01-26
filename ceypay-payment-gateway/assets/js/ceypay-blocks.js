( function( wc, wp ) {
    if ( ! wc || ! wc.wcBlocksRegistry ) {
        return;
    }

    var registerPaymentMethod = wc.wcBlocksRegistry.registerPaymentMethod;
    var el = wp.element.createElement;
    var __ = wp.i18n.__;
    var paymentMethodData = wc.wcSettings.getSetting( 'paymentMethodData', {} );
    var settings = paymentMethodData.ceypay || {};

    var CeyPayLabel = function( props ) {
        var iconSrc = settings.icons ? settings.icons.src : '';
        return el( 'span', { style: { display: 'flex', alignItems: 'center' } }, 
            iconSrc ? el( 'img', { 
                src: iconSrc, 
                alt: 'CeyPay', 
                style: { marginRight: '10px', height: '24px', verticalAlign: 'middle' } 
            } ) : null,
            settings.title || 'CeyPay'
        );
    };

    var CeyPayContent = function( props ) {
        var icons = settings.icons || {};
        var providers = [
            { name: 'Binance PAY', src: icons.binance, bgColor: '#FFF9E6', borderColor: '#F0B90B' },
            { name: 'PAY', src: icons.bybit, bgColor: '#FFF8E6', borderColor: '#FDB022' },
            { name: 'KuCoin Pay', src: icons.kucoin, bgColor: '#E6FFF5', borderColor: '#10F48B' },
            { name: 'Freedom PAY', src: icons.bitazza, bgColor: '#E6FFF5', borderColor: '#10F48B' },
            { name: 'TON', src: icons.ton, bgColor: '#E8F4FD', borderColor: '#0098EA' },
            { name: 'Solana', src: icons.solana, bgColor: '#F5E6FF', borderColor: '#9945FF' }
        ];

        return el( 'div', { className: 'ceypay-block-content' }, 
            el( 'div', { className: 'ceypay-description', style: { marginBottom: '10px', color: '#666' } }, 
                settings.description || 'Pay securely via CeyPay.' 
            ),
            el( 'div', { 
                className: 'ceypay-providers-chips', 
                style: { 
                    display: 'flex', 
                    gap: '6px', 
                    flexWrap: 'wrap',
                    alignItems: 'center'
                } 
            },
                providers.map( function( provider ) {
                    if ( ! provider.src ) return null;
                    return el( 'span', { 
                        key: provider.name,
                        className: 'ceypay-provider-chip',
                        style: { 
                            display: 'inline-flex', 
                            alignItems: 'center', 
                            gap: '5px',
                            padding: '4px 10px', 
                            backgroundColor: provider.bgColor, 
                            borderRadius: '4px', 
                            fontSize: '11px',
                            fontWeight: '500',
                            color: '#555',
                            border: '1px solid ' + provider.borderColor,
                            lineHeight: '1.4',
                            opacity: '0.88'
                        } 
                    },
                        el( 'img', { 
                            src: provider.src, 
                            alt: provider.name, 
                            style: { 
                                height: '12px', 
                                width: 'auto',
                                display: 'block',
                                opacity: '0.85'
                            } 
                        } ),
                        el( 'span', null, provider.name )
                    );
                } )
            )
        );
    };

    registerPaymentMethod( {
        name: 'ceypay',
        label: el( CeyPayLabel ),
        content: el( CeyPayContent ),
        edit: el( CeyPayContent ),
        canMakePayment: function() {
            return true;
        },
        ariaLabel: 'CeyPay',
        supports: {
            features: settings.supports || [],
        },
    } );
} )( window.wc, window.wp );
