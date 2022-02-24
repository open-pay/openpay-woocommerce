let $ = jQuery;
let prefix = 'woocommerce_openpay_cards_';
let fields =  [
    'affiliation_bbva',
    'charge_type',
    'capture',
    'use_card_points',
    'save_cc',
    'iva',
    'msi',
    'minimum_amount_interest_free',
    'msi_options_pe'
]
let countryCodeByCurrency = {
    'MXN': 'MX',
    'PEN': 'PE',
    'COP': 'CO',
    'ARS': 'AR'
}
let fieldsByCountry = {
    'MX': ['charge_type', 'capture', 'use_card_points', 'save_cc', 'msi', 'minimum_amount_interest_free'],
    'MXBBVA': ['charge_type', 'capture', 'use_card_points', 'save_cc', 'msi', 'minimum_amount_interest_free', 'affiliation_bbva'],
    'CO': ['save_cc', 'iva'],
    'PE': ['save_cc', 'capture', 'msi_options_pe'],
    'AR': ['save_cc']
}

function getSelectorField (field) {
    return `#${prefix}${field}`
}

function showOrHideElementsByCountry(country) {
    let fieldsCountry = fieldsByCountry[country];
    fields.forEach( field => {
        let selector = getSelectorField(field);
        fieldsCountry.includes(field) ? $(selector).closest("tr").show() : $(selector).closest("tr").hide();
    });
}

function getCountryCode() {
    let selectorCountry = getSelectorField('country');
    let country = $(selectorCountry).val();
    let selectorMerchantOrigin = getSelectorField('merchant_classification');
    let merchantOrigin = $(selectorMerchantOrigin).val();
    country = (country == 'MX' && merchantOrigin == 'eglobal') ? 'MXBBVA' : country;

    return country;
}
function is_sandbox(){
    let selectorSandbox = getSelectorField('sandbox');
    sandbox = $(selectorSandbox).is(':checked');
    
    if(sandbox){
        $("input[name*='live']").parent().parent().parent().hide();
        $("input[name*='test']").parent().parent().parent().show();
    }else{
        $("input[name*='test']").parent().parent().parent().hide();
        $("input[name*='live']").parent().parent().parent().show();
    }
}

$(getSelectorField('country')).change(function () {
    let countryCode = getCountryCode();
    showOrHideElementsByCountry(countryCode);
});

$(getSelectorField('sandbox')).on("change", function(e){
    is_sandbox();
});

$(document).ready(function () {
    /** This validate when you aren't in the settting openpay page */
    if (!$(getSelectorField('sandbox')).length ) return ; 
    
    $('#settings').removeClass('hidden');
    let selectorCurrenCurrency = getSelectorField('current_currency');
    $(selectorCurrenCurrency).closest('tr').hide();
    $(getSelectorField('merchant_classification')).closest("tr").hide();
    let selectorCountry = getSelectorField('country');
    let selectorMerchantId = getSelectorField('live_merchant_id');
    let selectorSandboxMerchantId = getSelectorField('sandbox_merchant_id');
    let merchantId = $(selectorMerchantId).val();
    let sandboxMerchantId = $(selectorSandboxMerchantId).val()
    
    let currency = $(selectorCurrenCurrency).val();    
    /** If merchant id in live or sandbox environment is empty set the settings in the country by the currency */
    let countryCode = ( merchantId == '' && sandboxMerchantId == '') ? countryCodeByCurrency[currency] : getCountryCode();
    $(selectorCountry).val(countryCode);
    showOrHideElementsByCountry(countryCode);
    is_sandbox();
});