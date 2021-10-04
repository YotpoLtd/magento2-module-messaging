define([
    'jquery',
    'mage/url'
], function ($, urlBuilder){
    'use strict';
    $.widget('mage.removeLocalStorage', {
        _init: function () {
            if(window.localStorage["mage-cache-storage"])
            {
                window.localStorage["mage-cache-storage"] = "";
            }
            urlBuilder.setBaseUrl(BASE_URL);
            window.location.href = urlBuilder.build("checkout/#payment");
        }
    });
    return $.mage.removeLocalStorage;
});
