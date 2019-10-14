jQuery(function() {
    var MAX_ZOOM_ON_LOAD = 16;
    var allStoresSelected = false;

    tomtom.setProductInfo('Store Locator', '1.0');
    var map = tomtom.L.map('map', {
        key: window.tomtomSdkKey,
        source: 'vector',
        basePath: tomtomSdkPath,
        center: [39.8283, -98.5795],
        zoom: 3.5
    });

    // if we've added a marker to the map, we'll store it
    // here so other functions can access it. Right now this
    // is only needed in the case where a user previews a new
    // store location but then cancels.
    var newMarker = undefined;
    var markers = [];
    if(storeLocations.length > 0) {
        storeLocations.forEach(store => addStoreMarkerToMap(store));
        var markerGroup = new tomtom.L.featureGroup(markers);
        fitMapToMarkerGroup(markerGroup)
    }

    jQuery('.ttlocator-add-store').click(function() {
        jQuery('.ttlocator-stores-table').hide();
        jQuery('.ttlocator-add-store-page').show();
    });

    jQuery('#ttlocator-select-all').click(function() {
        if(allStoresSelected) {
            // deselect all
            jQuery('.ttlocator-select-check').prop('checked', false);
            allStoresSelected = false;
            jQuery('#ttlocator-delete-selected').hide();
        } else {
            // select all
            jQuery('.ttlocator-select-check').prop('checked', true);
            allStoresSelected = true;
            jQuery('#ttlocator-delete-selected').show();
        }
    });

    jQuery('.ttlocator-select-check').click(function() {
        var check = jQuery(this);
        var allChecks = jQuery('.ttlocator-select-check');
        var totalChecks = allChecks.length;
        var numberChecked = allChecks.filter(':checked').length;
        if(check.prop('checked') === false) {
            // if we're deselecting one check, then it's impossible
            // for all to be checked
            jQuery('#ttlocator-select-all').prop('checked', false);
            allStoresSelected = false;
            if(numberChecked === 0) {
                jQuery('#ttlocator-delete-selected').hide();
            }
        } else {
            // to meet user expectations, we'll auto-check the 'select all'
            // checkbox if the user happens to manually select all rows
            if(totalChecks === numberChecked) {
                allStoresSelected = true;
                jQuery('#ttlocator-select-all').prop('checked', true);
            }
            jQuery('#ttlocator-delete-selected').show();
        }
    });

    jQuery('.ttlocator-add-store-cancel').click(function() {
        jQuery('.ttlocator-stores-table').show();
        jQuery('.ttlocator-add-store-page').hide();
        jQuery('.ttlocator-add-store-save').hide();
        jQuery('#ttlocator-store-lookup-messages').text('')
        clearEntryFields();
        fitMapToMarkerGroup(markerGroup);
        if(newMarker) {
            newMarker.remove();
            newMarker = undefined;
        }
    });

    jQuery('.ttlocator-lookup-button').click(function() {
        var query = jQuery("input[name='store-address']").val();
        tomtom.fuzzySearch()
            .key(window.tomtomSdkKey)
            .query(query)
            .go()
            .then(locateCallback)
            .catch(function(error) {
                console.log(error);
            });
    });

    jQuery('.ttlocator-add-store-save').click(function(){
        if(newStoreAddress) {
            saveNewStoreLocation();
        }
    });

    function fitMapToMarkerGroup(markerGroup) {
        map.fitBounds(markerGroup.getBounds().pad(0.2));
        if (map.getZoom() > MAX_ZOOM_ON_LOAD) {
            map.setZoom(MAX_ZOOM_ON_LOAD);
        }
    }

    function addStoreMarkerToMap(store) {
        var location = [store.latitude, store.longitude];
        var marker = tomtom.L.marker(location).addTo(map);
        marker.bindPopup("<b>" + store.name + "</b><br />" + store.address);
        markers.push(marker);
    }

    var newStoreAddress = {};
    function saveNewStoreLocation() {
        var data = {
            name: jQuery('input[name="store-name"]').val(),
            action: 'ttlocator_add_location',
            address: newStoreAddress.streetAddress,
            city: newStoreAddress.city,
            state: newStoreAddress.state,
            postcode: newStoreAddress.postCode,
            country: newStoreAddress.country,
            latitude: newStoreAddress.lat,
            longitude: newStoreAddress.lon
        };
        jQuery.post(ajaxurl, data)
            .done(function() {
                window.location = window.location;
        });
    }

    function locateCallback(result) {
        jQuery('#ttlocator-store-lookup-messages').text('');
        var filteredResult = result && result.filter(r => r.type === "Point Address") || [];
        if(filteredResult.length > 0) {
            jQuery('.ttlocator-add-store-save').show();
            var topResult = filteredResult[0];
            var address = topResult.address;
            var newStoreName = jQuery('input[name="store-name"]').val();
            // save new store address info so we can add it to database
            // after user confirms it is correct.
            newStoreAddress = {
                streetAddress: address.streetNumber + " " + address.streetName,
                city: address.municipality.split(",")[0],
                state: address.countrySubdivision,
                postCode: address.extendedPostalCode || address.postalCode,
                country: address.country,
                lat: topResult.position.lat,
                lon: topResult.position.lon
            };

            var location = [topResult.position.lat, topResult.position.lon];
            map.setView(location, 15);
            var marker = tomtom.L.marker(location).addTo(map);
            marker.bindPopup("<b>" + newStoreName + "</b><br />" + address.freeformAddress)
                .openPopup();
            newMarker = marker;
        } else {
            jQuery('#ttlocator-store-lookup-messages').text("Address not found. Try changing the address or adding more information, such as country and zip/postal code.")
        }
    }

    function clearEntryFields() {
        jQuery('input[name="store-name"]').val("")
        jQuery("input[name='store-address']").val("");
    }

    // initialize the store delete confirmation dialog
    jQuery('#delete-confirm').dialog({
        title: 'Delete Stores',
        dialogClass: 'wp-dialog',
        autoOpen: false,
        draggable: false,
        width: '400px',
        modal: true,
        resizable: false,
        closeOnEscape: true,
        position: {
            my: "center",
            at: "center",
            of: window
        },
        open: function () {
            // close dialog by clicking the overlay behind it
            jQuery('.ui-widget-overlay').bind('click', function(){
                jQuery('#delete-confirm').dialog('close');
            })
        },
        create: function () {
            // style fix for WordPress admin
            jQuery('.ui-dialog-titlebar-close').addClass('ui-button');
        },
    });

    // bind a button or a link to open the dialog
    jQuery('#ttlocator-delete-selected').click(function(e) {
        e.preventDefault();
        var allChecks = jQuery('.ttlocator-select-check');
        var totalChecks = allChecks.length;
        var numberChecked = allChecks.filter(':checked').length;
        jQuery("#store-deletion-count").text(numberChecked);
        jQuery('#delete-confirm').dialog('open');
    });

    jQuery("#ttlocator-perm-delete-cancel").click(function() {
        jQuery('#delete-confirm').dialog('close');
    });

    // delete all of the selected stores after user confirms
    jQuery('#ttlocator-perm-delete').click(function() {
        var selectedChecks = jQuery('.ttlocator-select-check').filter(':checked');
        var idsToDelete = [];
        selectedChecks.each(function(index, check) {
            var id = jQuery(check).attr('data-id');
            idsToDelete.push(id);
        });
        var data = {
            action: 'ttlocator_delete_locations',
            ids: idsToDelete.join(",")
        };
        jQuery.post(ajaxurl, data)
            .done(function() {
                window.location = window.location;
            });
    });
});