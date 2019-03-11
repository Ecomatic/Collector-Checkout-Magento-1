jQuery(document).ready(function () {
    document.addEventListener('collectorCheckoutLocked', function (event) {
        disabled = true;
    });
    document.addEventListener('collectorCheckoutUnlocked', function (event) {
        disabled = false;
    });
    jQuery("#ajax_topcart").hide();
    jQuery("#ajax_topcart").insertAfter(jQuery(".top-link-cart:first"));
    jQuery(".footer-container").next('a.top-link-cart').remove();

    jQuery(".page-popup").find('a.top-link-cart').remove();
    jQuery(".page-popup").find('div#ajax_topcart').remove();

});

var disabled = false;
var timer;
//jQuery('.top-link-cart').live("hover",function(){
jQuery('.top-link-cart').on("hover", function () {
    clearInterval(timer);
    jQuery('#ajax_topcart').show();
    timer = setInterval(hidediv, 8000);
});
//jQuery('#ajax_topcart').live("mouseenter",function(){
jQuery('#ajax_topcart').on("mouseenter", function () {
    clearInterval(timer);
});
//jQuery('#ajax_topcart').live("mouseleave",function(){
jQuery('#ajax_topcart').on("mouseleave", function () {
    timer = setInterval(hidediv, 8000);
});
//jQuery('#close_ajax_topcart').live("click",function(){
jQuery('#close_ajax_topcart').on("click", function () {
    jQuery('#ajax_topcart').hide();
});

function hidediv() {
    jQuery('#ajax_topcart').hide();
}

function displayTopCart() {
    clearInterval(timer);
    jQuery('#ajax_topcart').show();
    timer = setInterval(hidediv, 8000);
}

function showcartloading() {

    var href = jQuery(location).attr('href');

    if (href.search('checkout/cart') == -1) {
        jQuery(".minicart-wrapper").css("position", "relative");
        jQuery(".minicart-wrapper").append("<div style='position:absolute;top:0px;left:0px;right:0px;bottom:0px;height:100%;width:100%;background:white;margin:0px;-moz-opacity:.40;filter:alpha(opacity=40);opacity:0.4;z-index:888'></div>");
        var img = "<div style='position:absolute;top:25%;left:50%;margin-left:-33px;z-index:889'><img src='" + image + "'/></div>";
        jQuery(".minicart-wrapper").append(img);

    } else {

        jQuery(".cart").css("position", "relative");
        jQuery(".cart").append("<div style='position:absolute;top:0px;left:0px;right:0px;bottom:0px;height:100%;width:100%;background:white;margin:0px;-moz-opacity:.40;filter:alpha(opacity=40);opacity:0.4;z-index:888'></div>");
        var img = "<div style='position:absolute;top:25%;left:50%;margin-left:-33px;z-index:889'><img src='" + image + "'/></div>";
        jQuery(".cart").append(img);
    }
}

function showproductloading() {

    var href = jQuery(location).attr('href');
    jQuery(".category-products").css("position", "relative");
    jQuery(".category-products").append("<div id='pro-loading' style='position:absolute;top:0px;left:0px;right:0px;bottom:0px;height:100%;width:100%;background:white;margin:0px;-moz-opacity:.40;filter:alpha(opacity=40);opacity:0.4;z-index:888'></div>");
    var img = "<div id='pro-img' style='position:absolute;top:25%;left:50%;margin-left:-33px;z-index:889'><img src='" + image + "'/></div>";
    jQuery(".category-products").append(img);
}

function showviewloading() {
    jQuery(".product-view").css("position", "relative");
    jQuery(".product-view").append("<div id='pro-view-loading' style='position:absolute;top:0px;left:0px;right:0px;bottom:0px;height:100%;width:100%;background:white;margin:0px;-moz-opacity:.40;filter:alpha(opacity=40);opacity:0.4;z-index:888'></div>");
    var img = "<div id='pro-view-img' style='position:absolute;top:25%;left:50%;margin-left:-33px;z-index:889'><img src='" + image + "'/></div>";
    jQuery(".product-view").append(img);
}


function truncted_details() {
    $$('.truncated').each(function (element) {
        Event.observe(element, 'mouseover', function () {
            if (element.down('div.truncated_full_value')) {
                element.down('div.truncated_full_value').addClassName('show')
            }
        });
        Event.observe(element, 'mouseout', function () {
            if (element.down('div.truncated_full_value')) {
                element.down('div.truncated_full_value').removeClassName('show')
            }
        });
    });
}

function cartdelete(url, del_id) {
    if (disabled) {
        return;
    }
//		showcartloading();
    jQuery('#ajax_loader' + del_id).show();
    url = url.replace('checkout/cart/delete', 'ajax/ajax/headercartdelete/cart/delete');
    jQuery.ajax({
        url: url,
        type: "POST",
        dataType: "html",
        data: {btn_lnk: 1},
        beforeSend: function () {
            // Suspend the Checkout, showing a spinner...
            jQuery('body').addClass('is-suspended');
            try {
                window.collector.checkout.api.suspend();
            }
            catch (err) {
            }
        },
        success: function (data) {
            if (data == "\"redirect\"") {
                window.location.href = window.location.protocol + "//" + window.location.host + "/";
            }
            jQuery('#ajax_loader' + del_id).hide();
            jQuery("#overlay").hide();
            var result = jQuery(data);

            ajax_after_delete = jQuery(result).find('.ajax_after_delete');
            jQuery('.ajax_after_delete').html(ajax_after_delete);

            data_top_link = jQuery(result).find('div#cart_content');
            jQuery('.top-link-cart').html(data_top_link);

            data_top_cart = jQuery(result).find('.header-minicart');
            jQuery(".header-minicart").html(data_top_cart);


            truncted_details();
            displayTopCart();

            region_id();
            data_cart_content = jQuery(result).find('div#ajax_cart_content').html();
            jQuery('.cart').replaceWith(data_cart_content);


            $j('.skip-link').on('click', function (e) {
                e.preventDefault();
                location.reload();
                var self = $j(this);
                // Use the data-target-element attribute, if it exists. Fall back to href.
                var target = self.attr('data-target-element') ? self.attr('data-target-element') : self.attr('href');

                // Get target element
                var elem = $j(target);

                // Check if stub is open
                var isSkipContentOpen = elem.hasClass('skip-active') ? 1 : 0;

                // Hide all stubs
                $j('.skip-link').removeClass('skip-active');
                $j(".skip-content").removeClass('skip-active');

                // Toggle stubs
                if (isSkipContentOpen) {
                    self.removeClass('skip-active');
                } else {
                    self.addClass('skip-active');
                    elem.addClass('skip-active');
                }
            });
            //region_updater();
            //fancybox_update();
        },
        complete: function () {
            // ... and finally resume the Checkout after the backend call is completed to update the checkout
            jQuery('body').removeClass('is-suspended');
            try {
                window.collector.checkout.api.resume();
            }
            catch (err) {
            }
        },
    });

}

function updateheaderCart(item, qty) {
    if (disabled) {
        return;
    }
    jQuery('#ajax_loader' + item).show();
//        showcartloading();
    url = url_update;
    jQuery.ajax({
        url: url,
        type: "POST",
        dataType: "html",
        data: {"item": item, "qty": qty},
        beforeSend: function () {
            // Suspend the Checkout, showing a spinner...
            jQuery('body').addClass('is-suspended');
            try {
                window.collector.checkout.api.suspend();
            }
            catch (err) {
            }
        },
        success: function (data) {
            jQuery('#ajax_loader' + item).hide();
            var result = jQuery(data);

            data_top_link = jQuery(result).find('div#cart_content').text();
            jQuery('.top-link-cart').html(data_top_link);

            data_top_cart = jQuery(result).find('div#ajax_topcart');
            jQuery("#ajax_topcart").replaceWith(data_top_cart);

            truncted_details();
            displayTopCart();

            region_id();
            data_cart_content = jQuery(result).find('div#ajax_cart_content').html();
            jQuery('.cart').replaceWith(data_cart_content);
            region_updater();

            //fancybox_update();
        },
        complete: function () {
            // ... and finally resume the Checkout after the backend call is completed to update the checkout
            jQuery('body').removeClass('is-suspended');
            try {
                window.collector.checkout.api.resume();
            }
            catch (err) {
            }
        },
    });

}

function updateCart(item, qty) {
    if (disabled) {
        return;
    }
    alert(item);
    alert(qty);
    jQuery('#ajax_loader_' + item).show();
//        showcartloading();
    var url = url_update_shoopingcart;
    jQuery.ajax({
        url: url,
        type: "POST",
        dataType: "html",
        data: {"item": item, "qty": qty},
        success: function (data) {
            jQuery('#ajax_loader' + item).hide();
            var result = jQuery(data);

            var href = jQuery(location).attr('href');

            region_id();
            data_cart_main_content = jQuery(result).find('div.cart');
            jQuery('.cart').replaceWith(data_cart_main_content);
            region_updater();

            var url = url_topcart;

            jQuery.ajax({
                url: url,
                type: "POST",
                data: {},
                success: function (data) {
                    var returndata = data;
                    jQuery('.top-link-cart:first').parent("li").html(returndata);
                    truncted_details();
                    displayTopCart();
                }
            });
            //fancybox_update();
        }
    });
}

function emptyCart() {
    if (disabled) {
        return;
    }
//            showcartloading();
    var url = url_update_shoopingcart;
    jQuery.ajax({
        url: url,
        type: "POST",
        dataType: "html",
        data: {action: 'empty_cart'},
        success: function (data) {
            var result = jQuery(data);
            data_cart_main_content = jQuery(result).find('div.col-main').html();
            jQuery('.cart').replaceWith(data_cart_main_content);
            data_top_link = jQuery(result).find('.header-minicart');
            jQuery('.header-minicart').html(data_top_link);
            var url = url_topcart;

            jQuery.ajax({
                url: url,
                type: "POST",
                data: {},
                beforeSend: function () {
                    // Suspend the Checkout, showing a spinner...
                    jQuery('body').addClass('is-suspended');
                    try {
                        window.collector.checkout.api.suspend();
                    }
                    catch (err) {
                    }
                },
                success: function (data) {
                    var returndata = data;
                    jQuery('.top-link-cart:first').parent("li").html(returndata);
                    truncted_details();
                    displayTopCart();
                },
                complete: function () {
                    // ... and finally resume the Checkout after the backend call is completed to update the checkout
                    jQuery('body').removeClass('is-suspended');
                    try {
                        window.collector.checkout.api.resume();
                    }
                    catch (err) {
                    }
                },
            });
            //fancybox_update();
        }
    });
}


function region_id() {
    var href = jQuery(location).attr('href');
    if (href.search('checkout/cart') != -1) {
        //$('region_id').setAttribute('defaultValue', estimateRegionId);
    }
}

function region_updater() {
    var href = jQuery(location).attr('href');
    if (href.search('checkout/cart') != -1) {
        //new RegionUpdater('country', 'region', 'region_id',region_json);
    }
}


/* for update cart using ajax on cart page */

function editProduct() {
    if (disabled) {
        return;
    }
    var form_url = jQuery("#product_addtocart_form").attr("action");
    showviewloading();
    jQuery.ajax({
        url: form_url,
        type: "POST",
        dataType: "html",
        data: jQuery('form').serialize(),
        success: function (data) {
            url = cart_url;
            jQuery.ajax({
                url: url,
                type: "POST",
                dataType: "html",
                data: {},
                success: function (data) {
                    var result = data
                    jQuery("#pro-view-loading").remove();
                    jQuery("#pro-view-img").remove();

                    data_top_link = jQuery(result).find('.top-link-cart').html();
                    parent.jQuery('.top-link-cart').html(data_top_link);

                    data_top_cart = jQuery(result).find('.header-minicart').html();

                    // alert(data_top_cart);
                    parent.jQuery(".header-minicart").html(data_top_cart);

                    parent.region_id();
                    data_cart_content = jQuery(result).find('div.cart').html();
                    parent.jQuery('div.cart').html(data_cart_content);
                    parent.region_updater();

                    parent.truncted_details();
                    parent.displayTopCart();
                    //parent.fancybox_update();
                    parent.jQuery.fancybox.close();


                }
            });
        }
    });
}

/* For Apply Coupon using ajax on cart page */

function discountCoupon(coupon_form_url, isremove) {
    if (disabled) {
        return;
    }
    if (isremove == '1') {
        $('coupon_code').removeClassName('required-entry');
        $('remove-coupone').value = "1";
    } else {
        $('coupon_code').addClassName('required-entry');
        $('remove-coupone').value = "0";
    }

//        showcartloading();
    //alert(jQuery('#discount-coupon-form').serialize());
    jQuery.ajax({
        url: coupon_form_url,
        type: 'POST',
        data: jQuery('form').serialize(),
        beforeSend: function () {
            // Suspend the Checkout, showing a spinner...
            jQuery('body').addClass('is-suspended');
            try {
                window.collector.checkout.api.suspend();
            }
            catch (err) {
            }
        },
        success: function (data) {
            var result = data;
            //$('region_id').setAttribute('defaultValue', estimateRegionId);
            data_total_box = jQuery(result).find('div.cart').html();
            jQuery('div.cart').html(data_total_box);
            //new RegionUpdater('country', 'region', 'region_id',region_json);
        },
        complete: function () {
            // ... and finally resume the Checkout after the backend call is completed to update the checkout
            jQuery('body').removeClass('is-suspended');
            try {
                window.collector.checkout.api.resume();
            }
            catch (err) {
            }
        },
    });
}


function qtyincrese(url, item, prev_qty, obj) {
    if (disabled) {
        return;
    }
    var qty = jQuery(obj).parent('div.qty-wrapper').find('.qty').val();
//	showproductloading(obj);
//	showcartloading();
    jQuery.ajax({
        url: url,
        type: 'post',
        dataType: 'json',
        data: {
            item_id: item,
            item_qty: qty,
            prevQty: prev_qty
        },
        beforeSend: function () {
            // Suspend the Checkout, showing a spinner...
            jQuery('body').addClass('is-suspended');
            try {
                window.collector.checkout.api.suspend();
            }
            catch (err) {
            }
        },
        success: function (data) {
            if (data.cart_content_ajax) {
                jQuery('div.cart').replaceWith(data.cart_content_ajax);
            }
            if (data.header_cart) {
                jQuery('.shopping-cart').replaceWith(data.header_cart);
            }
            if (data.min_cart) {
                jQuery('.header-minicart').html(data.min_cart);
            }
            if (data.smart_min_cart) {
                jQuery('.mini-cart').replaceWith(data.smart_min_cart);
            }
            if (data.cart_sidebar) {
                jQuery('.col-right .block-cart').html(data.cart_sidebar);
            }
            if (data.iframe_ajax) {
                var scriptEl = document.createElement('script');
                scriptEl.setAttribute('src', data.iframe_ajax.src);
                scriptEl.async = true;
                scriptEl.setAttribute('id', data.iframe_ajax.id);
                scriptEl.setAttribute('data-lang', data.iframe_ajax.datalang);
                scriptEl.setAttribute('data-token', data.iframe_ajax.datatoken.message);
                scriptEl.setAttribute('data-variant', data.iframe_ajax.datavariant);
                document.getElementById('collectoriframe').innerHTML = "";
                document.getElementById('collectoriframe').appendChild(scriptEl);
            }

            if (data.status == "ERROR") {
                jQuery('div.message').replaceWith('<div class="error-message" style="display:block;background-color:red;">' + data.message + '</div>')
            }
            jQuery("#pro-loading").remove();
            jQuery("#pro-img").remove();
            jQuery("#qinput-" + item).val(1);

            //updateScript();

            if (data.message)
            //alert(data.message);

                $j('.skip-link').on('click', function (e) {
                    e.preventDefault();

                    var self = $j(this);
                    // Use the data-target-element attribute, if it exists. Fall back to href.
                    var target = self.attr('data-target-element') ? self.attr('data-target-element') : self.attr('href');

                    // Get target element
                    var elem = $j(target);

                    // Check if stub is open
                    var isSkipContentOpen = elem.hasClass('skip-active') ? 1 : 0;

                    // Hide all stubs
                    $j('.skip-link').removeClass('skip-active');
                    $j(".skip-content").removeClass('skip-active');

                    // Toggle stubs
                    if (isSkipContentOpen) {
                        self.removeClass('skip-active');
                    } else {
                        self.addClass('skip-active');
                        elem.addClass('skip-active');
                    }
                });

            $j('#header-cart').on('click', '.skip-link-close', function (e) {
                var parent = $j(this).parents('.skip-content');
                var link = parent.siblings('.skip-link');

                parent.removeClass('skip-active');
                link.removeClass('skip-active');

                e.preventDefault();
            });


        },
        complete: function () {
            // ... and finally resume the Checkout after the backend call is completed to update the checkout
            jQuery('body').removeClass('is-suspended');
            try {
                window.collector.checkout.api.resume();
            }
            catch (err) {
            }
        },
    });

}


function qtydecrese(url, item, prev_qty, obj) {
    if (disabled) {
        return;
    }
    var qty = jQuery(obj).parent('div.qty-wrapper').find('.qty').val();
//	showproductloading(obj);
//	showcartloading();
    jQuery.ajax({
        url: url,
        type: 'post',
        dataType: 'json',
        data: {
            item_id: item,
            item_qty: qty,
            prevQty: prev_qty
        },
        beforeSend: function () {
            // Suspend the Checkout, showing a spinner...
            jQuery('body').addClass('is-suspended');
            try {
                window.collector.checkout.api.suspend();
            }
            catch (err) {
            }
        },
        success: function (data) {
            if (data.cart_content_ajax) {
                jQuery('div.cart').replaceWith(data.cart_content_ajax);
            }
            if (data.header_cart) {
                jQuery('.shopping-cart').replaceWith(data.header_cart);
            }
            if (data.min_cart) {
                jQuery('.header-minicart').html(data.min_cart);
            }
            if (data.smart_min_cart) {
                jQuery('.mini-cart').replaceWith(data.smart_min_cart);
            }
            if (data.cart_sidebar) {
                jQuery('.col-right .block-cart').html(data.cart_sidebar);
            }
            if (data.iframe_ajax) {
                window.location.href = window.location.protocol + "//" + window.location.host + "/checkout/cart";
            }
            jQuery("#pro-loading").remove();
            jQuery("#pro-img").remove();
            jQuery("#qinput-" + item).val(1);
            if (data.message)
            //alert(data.message);
                $j('.skip-link').on('click', function (e) {
                    e.preventDefault();

                    var self = $j(this);
                    // Use the data-target-element attribute, if it exists. Fall back to href.
                    var target = self.attr('data-target-element') ? self.attr('data-target-element') : self.attr('href');

                    // Get target element
                    var elem = $j(target);

                    // Check if stub is open
                    var isSkipContentOpen = elem.hasClass('skip-active') ? 1 : 0;

                    // Hide all stubs
                    $j('.skip-link').removeClass('skip-active');
                    $j(".skip-content").removeClass('skip-active');

                    // Toggle stubs
                    if (isSkipContentOpen) {
                        self.removeClass('skip-active');
                    } else {
                        self.addClass('skip-active');
                        elem.addClass('skip-active');
                    }
                });
        },
        complete: function () {
            // ... and finally resume the Checkout after the backend call is completed to update the checkout
            try {
                window.collector.checkout.api.resume();
            }
            catch (err) {
            }
            jQuery('body').removeClass('is-suspended');
        },
    });
    var skipContents = $j('.skip-content');
    var skipLinks = $j('.skip-link');

    skipLinks.on('click', function (e) {
        e.preventDefault();

        var self = $j(this);
        // Use the data-target-element attribute, if it exists. Fall back to href.
        var target = self.attr('data-target-element') ? self.attr('data-target-element') : self.attr('href');

        // Get target element
        var elem = $j(target);

        // Check if stub is open
        var isSkipContentOpen = elem.hasClass('skip-active') ? 1 : 0;

        // Hide all stubs
        skipLinks.removeClass('skip-active');
        skipContents.removeClass('skip-active');

        // Toggle stubs
        if (isSkipContentOpen) {
            self.removeClass('skip-active');
        } else {
            self.addClass('skip-active');
            elem.addClass('skip-active');
        }
    });


    $j('#header-cart').on('click', '.skip-link-close', function (e) {
        var parent = $j(this).parents('.skip-content');
        var link = parent.siblings('.skip-link');

        parent.removeClass('skip-active');
        link.removeClass('skip-active');

        e.preventDefault();
    });
}
