console.log('cart custom js 25 Mar 1-51 pm');
console.log(userCartMembership.offer_available);
console.log(userCartMembership.offer_used);

// Observer callback to execute when mutations are observed
const observerCallback = (mutations, observer) => {
    for (const mutation of mutations) {
        if (mutation.type === 'childList' && mutation.addedNodes.length) {
            mutation.addedNodes.forEach(node => {
                if (node.nodeType === 1 && jQuery(node).find('.cart-item-details span:last-child').length) {  // Checks if the node is an element and has the target span
                    console.log("Target element has been added.");
                    observer.disconnect();  // Stop observing once we have our element
                    updatePricesBasedOnMembership();
                }
            });
        }
    }
};

// Set up the MutationObserver
const observer = new MutationObserver(observerCallback);
const config = { childList: true, subtree: true };

// Start observing
jQuery(window).on('load', function() {
    // Ensure the body element is present to observe changes
    const targetNode = document.body;  // You might want to be more specific depending on your DOM structure
    if (targetNode) {
        observer.observe(targetNode, config);
    }
});



function updatePricesBasedOnMembership() {
    var productId,cartDetails;
    if (typeof ajax_object === 'undefined' || !ajax_object.ajax_url) {
        console.error("AJAX Object is not defined. Check if wp_localize_script is working.");
        return;
    }

    jQuery('.cart-item-pricing').each(function() {
        productId = jQuery(this).attr('product-id'); // Get product-id from attribute
        
        if (productId) {
            console.log("Product ID found: " + productId);
            cartDetails = getCartDetails();  // Get member quantities
            // Send AJAX request to fetch product details
            fetchProductDetails(productId,cartDetails);

        }
    });
}

// Function to send AJAX request
function fetchProductDetails(productId,cartDetails) {
    var member, membership_adult_quantity, membership_child_quantity,adult_quantity,child_quantity,calculatedRates,totalOriginalPrice;

    member = userCartMembership.member;
    adult_quantity = cartDetails.totalAdults;
    child_quantity = cartDetails.totalChildren;
    totalOriginalPrice = cartDetails.totalPrice;

    console.log('Total Adults: ', adult_quantity);

    jQuery.ajax({
        url: ajax_object.ajax_url, // WP AJAX URL
        type: 'POST',
        data: {
            action: 'get_product_details', // PHP function to handle request
            product_id: productId
        },
        success: function(response) {

            if (response.success) {
                let productDetails = response.data.productDetails;
                console.log("productDetails Details Received:", productDetails);
                console.log("cartDetails Details Received:", cartDetails);
                membership_adult_quantity = productDetails.membership_adult_quantity 
                membership_child_quantity = productDetails.membership_child_quantity 

                if (adult_quantity === 0  && child_quantity === 0) {
                    console.warn("Both adult and child quantities cannot be zero at the same time.");
                }else{
                    if (member !== 'default' && !(adult_quantity === 0 && child_quantity === 0)) {
                        if (adult_quantity <= membership_adult_quantity && child_quantity <= membership_child_quantity) {
                            console.log("calculate membership rate");
                            let membershipDetails = { 
                                ...productDetails,
                                adult_quantity: adult_quantity,
                                child_quantity: child_quantity
                            };
            
                            calculatedRates = calculateMemberRates(membershipDetails);
                            updatePricingUI(calculatedRates,totalOriginalPrice,'membership');
                        } else {
                            console.warn("Selected quantity exceeds membership limits. Applying extra charge for additional quantities.");
                            
                            let extra_adults = Math.max(0, adult_quantity - membership_adult_quantity);
                            let extra_children = Math.max(0, child_quantity - membership_child_quantity);
                            
                            // Calculate the original membership-allowed rates
                            let applyMembershipDetails = { 
                                ...productDetails,
                                adult_quantity: Math.min(adult_quantity, membership_adult_quantity),
                                child_quantity: Math.min(child_quantity, membership_child_quantity)
                                
                            };
            
                            let memberRates = calculateMemberRates(applyMembershipDetails);
                    
                            // Create a new cart details object for non-membership calculation
                            let extraMemberDetails = { ...productDetails };
                            extraMemberDetails.adult_quantity = extra_adults;
                            extraMemberDetails.child_quantity = extra_children;
                            
                            let nonMemberRates = calculateNonMemberRates(extraMemberDetails);
                            
                            // Merge both rates by adding corresponding values
                            console.log("memberRates Rates:", memberRates);
                            console.log("nonMemberRates Rates:", nonMemberRates);
                            let finalRates = {};
                            Object.keys(memberRates).forEach(key => {
                                finalRates[key] = (memberRates[key] || 0) + (nonMemberRates[key] || 0);
                            });
                            
                            console.log("Final Combined Rates:", finalRates);
                            updatePricingUI(finalRates, totalOriginalPrice,'extra-member');
                        }
                    } else if (member === 'default') {
                        console.log("calculate non membership rate");
                        let nonMembershipDetails = { 
                            ...productDetails,
                            adult_quantity: adult_quantity,
                            child_quantity: child_quantity
                        };
        
                        console.log('nonMember Details----',nonMembershipDetails);
                    
                        calculatedRates = calculateNonMemberRates(nonMembershipDetails);
                        updatePricingUI(calculatedRates, totalOriginalPrice,'non-member');
                    }
                    
                }
                
            } else {
                console.error("Error fetching product details.");
            }
        },
        error: function(xhr, status, error) {
            console.error("AJAX Error:", error);
        }
    });
}

function getCartDetails() {
    var detailsSpan = jQuery('.cart-item-details span').first().text();
    var adults = detailsSpan.match(/Adults × (\d+)/);
    var children = detailsSpan.match(/Children × (\d+)/);
    var totalAdults = adults ? parseInt(adults[1]) : 0;
    var totalChildren = children ? parseInt(children[1]) : 0;

    // Extract individual prices
    var adultPrice = 0;
    var childPrice = 0;
    var totalOriginalPrice = 0;

    jQuery('.cart-item-pricing .pricing-row').each(function() {
        var labelText = jQuery(this).find('.label').text().trim();
        var priceText = jQuery(this).find('.price').text().trim();
        var priceMatch = priceText.match(/(\d+(\.\d+)?)/);  // Match numbers with optional decimals
        
        if (priceMatch) {
            var priceValue = parseFloat(priceMatch[0]);

            if (labelText.includes('Adults')) {
                adultPrice = priceValue;
            } else if (labelText.includes('Children')) {
                childPrice = priceValue;
            }

            totalOriginalPrice += priceValue;
        }
    });

    // Log the details
    console.log({
        "Total Price": totalOriginalPrice,
        "Total Adults": totalAdults,
        "Total Children": totalChildren,
        "Total Quantity": totalAdults + totalChildren,
        "Adult Price": adultPrice,
        "Child Price": childPrice
    });

    // Return the values
    return {
        totalAdults: totalAdults,
        totalChildren: totalChildren,
        totalQtn: totalAdults + totalChildren,
        totalPrice: totalOriginalPrice,
        adultPrice: adultPrice,
        childPrice: childPrice
    };
}

function calculateMemberRates(productDetails) {
    var everythingCostaRicaAdultRate = 0;
    var everythingCostaRicaChildRate = 0;
    var totalAdultCost = 0;
    var totalChildCost = 0;

    let {
        post_id,
        membership_level,
        adult_price,
        child_price,
        commission_discount,
        net_rate_adult,
        net_rate_child,
        adult_quantity,
        child_quantity,
        membership_adult_quantity,
        membership_child_quantity
    } = productDetails;

    // Convert values to numbers (to prevent string issues)
    membership_level = parseFloat(membership_level);
    adult_price = parseFloat(adult_price);
    child_price = parseFloat(child_price);
    commission_discount = parseInt(commission_discount);
    net_rate_adult = parseFloat(net_rate_adult);
    net_rate_child = parseFloat(net_rate_child);
    adult_quantity = parseInt(adult_quantity);
    child_quantity = parseInt(child_quantity);
    membership_adult_quantity = parseInt(membership_adult_quantity);
    membership_child_quantity = parseInt(membership_child_quantity);

     // Convert commission_discount to percentage
     commission_percentage = commission_discount / 100;

    if(adult_quantity > 0 ){
        // Calculate Vendor Profit (same as Net Rate)
        let vendorProfitAdult = net_rate_adult;
        let ourCommissionAdult = adult_price - vendorProfitAdult;

        // Calculate everything_costa_rica_rates (Applying 25% Reduction in Commission)
        let finalCommissionAdult = ourCommissionAdult - (ourCommissionAdult * commission_percentage);
        
        everythingCostaRicaAdultRate = net_rate_adult + finalCommissionAdult;
        // Total cost for all adults			
        totalAdultCost = everythingCostaRicaAdultRate * adult_quantity;
    }

    if(child_quantity > 0){
        // Calculate Vendor Profit (same as Net Rate)
        let vendorProfitChild = net_rate_child;
        let ourCommissionChild = child_price - vendorProfitChild;

        // Calculate everything_costa_rica_rates (Applying 25% Reduction in Commission)
        let finalCommissionChild = ourCommissionChild - (ourCommissionChild * commission_percentage);
                
        everythingCostaRicaChildRate = net_rate_child + finalCommissionChild;
        // Total cost for all adults
        totalChildCost =  everythingCostaRicaChildRate * child_quantity;

    }
    
    // Calculate deposit_amount (Applying Membership Discount to Only Membership Quantities)
    let depositAmount = 
        ((everythingCostaRicaAdultRate - net_rate_adult) * adult_quantity) - 
        (((everythingCostaRicaAdultRate - net_rate_adult) * membership_adult_quantity) * membership_level) +
        ((everythingCostaRicaChildRate - net_rate_child) * child_quantity) - 
        (((everythingCostaRicaChildRate - net_rate_child) * membership_child_quantity) * membership_level);

    // Ensure deposit_amount is never negative
    if (child_quantity === 0) {
        depositAmount = Math.max(0, ((everythingCostaRicaAdultRate - net_rate_adult) * adult_quantity) -
            (((everythingCostaRicaAdultRate - net_rate_adult) * membership_adult_quantity) * membership_level));
    } else {
        depositAmount = Math.max(0, depositAmount);
    }

    // Calculate Due on Arrival Amount (Same as Before)
    let dueOnArrival = (net_rate_adult * adult_quantity) + (net_rate_child * child_quantity);
    let totalCostaRicaRate = totalAdultCost + totalChildCost;

    let rates = {
        "everything_costa_rica_adult_rate": everythingCostaRicaAdultRate,
        "everything_costa_rica_child_rate": everythingCostaRicaChildRate,
        "totalCostaRicaRate": totalCostaRicaRate,
        "deposit_amount": depositAmount,
        "due_on_arrival": dueOnArrival
    };

    console.log("Calculated Rates:", rates);
    return rates;
}


function calculateNonMemberRates(productDetails) {
    var everythingCostaRicaAdultRate = 0;
    var everythingCostaRicaChildRate = 0;
    var totalAdultCost = 0;
    var totalChildCost = 0;

    let {
        post_id,
        adult_price,
        child_price,
        commission_discount,
        net_rate_adult,
        net_rate_child,
        adult_quantity,
        child_quantity
    } = productDetails;

    // Convert values to numbers (to prevent string issues)
    adult_price = parseFloat(adult_price);
    child_price = parseFloat(child_price);
    commission_discount = parseInt(commission_discount);
    net_rate_adult = parseFloat(net_rate_adult);
    net_rate_child = parseFloat(net_rate_child);
    adult_quantity = parseInt(adult_quantity);
    child_quantity = parseInt(child_quantity);

    // Convert commission_discount to percentage
    commission_percentage = commission_discount / 100;

    if(adult_quantity > 0 ){
        let vendorProfitAdult = net_rate_adult;
        let ourCommissionAdult = adult_price - vendorProfitAdult;

        // Calculate everything_costa_rica_rates (Applying 25% Reduction in Commission)
        let finalCommissionAdult = ourCommissionAdult - (ourCommissionAdult * 0.25);	
                
        everythingCostaRicaAdultRate = net_rate_adult + finalCommissionAdult;
        // Total cost for all adults
        totalAdultCost = everythingCostaRicaAdultRate * adult_quantity;

    }
            
    if(child_quantity > 0){
        let vendorProfitChild = net_rate_child;
        let ourCommissionChild = child_price - vendorProfitChild;

        // Calculate everything_costa_rica_rates (Applying 25% Reduction in Commission)
        let finalCommissionChild = ourCommissionChild - (ourCommissionChild * commission_percentage);	
                
        everythingCostaRicaChildRate = net_rate_child + finalCommissionChild;

        // Total cost for all adults
        totalChildCost   = everythingCostaRicaChildRate * child_quantity;

    }

    // Correct deposit_amount Calculation
    let depositAmount = 
        ((everythingCostaRicaAdultRate - net_rate_adult) * adult_quantity) +
        ((everythingCostaRicaChildRate - net_rate_child) * child_quantity);

    let dueOnArrival = (net_rate_adult * adult_quantity) + (net_rate_child * child_quantity);
    let totalCostaRicaRate = totalAdultCost + totalChildCost;

    let rates = {
        "everything_costa_rica_adult_rate": everythingCostaRicaAdultRate,
        "everything_costa_rica_child_rate": everythingCostaRicaChildRate,
        "totalCostaRicaRate": totalCostaRicaRate,
        "deposit_amount": depositAmount,
        "due_on_arrival": dueOnArrival
    };

    console.log("Calculated Non-Member Rates:", rates);
    return rates;
}

function updatePricingUI(rates,totalOriginalPrice,member) {
    console.log('totalCostaRicaRate',rates["totalCostaRicaRate"]);
    console.log('due_on_arrival--',rates["due_on_arrival"]);
    console.log('deposit_amount--',rates["deposit_amount"]);

    // You can now update UI elements dynamically using productData
    jQuery('.costa-rate .ts-item-price p').text('$' + rates["totalCostaRicaRate"].toFixed(2));
    jQuery('.ts-total .ts-item-price p').text('$' + rates["due_on_arrival"].toFixed(2));
    jQuery('.due-today .ts-item-price p').text('$' + rates["deposit_amount"]);

    console.log("Updated Pricing UI with:", rates);
}




