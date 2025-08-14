(function ($) {
  'use strict';

  console.log('single page js 24 Mar 4-10 pm');
  console.log('Membership product page', userMembership.productDetails);

  jQuery(document).ready(function ($) {
    var targetElement = document.querySelector('.ts-total .ts-item-price p');

    if (!targetElement) {
      console.log('Target element not found.');
      // return;
    }

    // Callback function to execute when mutations are observed
    var callback = function (mutationsList, observer) {
      for (let mutation of mutationsList) {
        if (mutation.type === 'childList') {
          //applyDiscountAndUpdatePrice()
          applyUpdatePrice();
          console.log('A child node has been added or removed.');
        }
        if (mutation.type === 'characterData') {
          console.log(
            'The character data within the observed node has changed.',
          );
        }
        if (mutation.type === 'attributes') {
          console.log('The attributes of the observed node have changed.');
        }
      }
    };

    // Create an instance of MutationObserver with the callback
    var generalObserver = new MutationObserver(callback);

    // Options for the observer (which mutations to observe)
    var config = {
      attributes: true, // Observe attribute changes
      childList: true, // Observe direct children addition/removal
      subtree: true, // Observe all descendants (needed for text changes in child elements)
      characterData: true, // Observe changes to text
    };

    // Start observing the target element
    generalObserver.observe(document.body, config);

    function applyUpdatePrice() {
      var member = userMembership.member;
      var membership_adult_quantity,
        membership_child_quantity,
        adult_quantity,
        child_quantity,
        calculatedRates,
        totalOriginalPrice;
      var productDetails = userMembership.productDetails; // Get product details from userMembership
      if (!productDetails) {
        console.log('Cart details not available.');
        return;
      }

      console.log(member);
      console.log(productDetails.user_id);

      generalObserver.disconnect();
      let cartDetails = getCartDetails(); // Get quantities (adults, children, total)

      // Update `productDetails` with calculated values
      membership_adult_quantity = productDetails.membership_adult_quantity;
      membership_child_quantity = productDetails.membership_child_quantity;

      adult_quantity = cartDetails.totalAdults;
      child_quantity = cartDetails.totalChildren;
      totalOriginalPrice = cartDetails.totalOriginalPrice;

      console.log('adult_quantity--', adult_quantity);
      console.log('child_quantity --', child_quantity);

      // Validate that cart quantity does not exceed membership limits
      console.log('cartDetails Details Received:', cartDetails);
      console.log('productDetails Details Received:', productDetails);

      $('.membership-banner').css({
        display: 'none',
      });

      if (
        member === '' ||
        member === 'undefined' ||
        member === 0 ||
        member === 'null'
      ) {
        console.log('non logged in');
        nonLoggedUserDetails(cartDetails, productDetails);
      } else {
        if (adult_quantity === 0 && child_quantity === 0) {
          console.warn(
            'Both adult and child quantities cannot be zero at the same time.',
          );
        } else {
          console.log('right condition');
          if (
            member !== 'default' &&
            !(adult_quantity === 0 && child_quantity === 0)
          ) {
            if (
              adult_quantity <= membership_adult_quantity &&
              child_quantity <= membership_child_quantity
            ) {
              console.log('calculate membership rate');
              let membershipDetails = {
                ...productDetails,
                adult_quantity: adult_quantity,
                child_quantity: child_quantity,
              };

              calculatedRates = calculateMemberRates(
                membershipDetails,
                productDetails.membership_level,
              );
              updatePricingUI(
                calculatedRates,
                totalOriginalPrice,
                'membership',
              );
            } else {
              console.warn(
                'Selected quantity exceeds membership limits. Applying extra charge for additional quantities.',
              );

              let extra_adults = Math.max(
                0,
                adult_quantity - membership_adult_quantity,
              );
              let extra_children = Math.max(
                0,
                child_quantity - membership_child_quantity,
              );

              // Calculate the original membership-allowed rates
              let applyMembershipDetails = {
                ...productDetails,
                adult_quantity: adult_quantity,
                child_quantity: child_quantity,
                // adult_quantity: Math.min(
                //   adult_quantity,
                //   membership_adult_quantity,
                // ),
                // child_quantity: Math.min(
                //   child_quantity,
                //   membership_child_quantity,
                // ),
              };

              let memberRates = calculateMemberRates(
                applyMembershipDetails,
                productDetails.membership_level,
              );

              // Create a new cart details object for non-membership calculation
              let extraMemberDetails = { ...productDetails };
              extraMemberDetails.adult_quantity = extra_adults;
              extraMemberDetails.child_quantity = extra_children;

              // let nonMemberRates = calculateNonMemberRates(extraMemberDetails); // not needed

              // Merge both rates by adding corresponding values
              console.log('memberRates Rates:', memberRates);
              // console.log('nonMemberRates Rates:', nonMemberRates);
              let finalRates = {};
              Object.keys(memberRates).forEach((key) => {
                finalRates[key] =
                  // (memberRates[key] || 0) + (nonMemberRates[key] || 0);
                  (memberRates[key] || 0);
              });

              console.log('Final Combined Rates:', finalRates);
              updatePricingUI(finalRates, totalOriginalPrice, 'extra-member');
            }
          } else if (member === 'default') {
            console.log('calculate non membership rate');
            let nonMembershipDetails = {
              ...productDetails,
              adult_quantity: adult_quantity,
              child_quantity: child_quantity,
            };

            console.log('nonMember Details----', nonMembershipDetails);

            calculatedRates = calculateNonMemberRates(nonMembershipDetails);
            updatePricingUI(calculatedRates, totalOriginalPrice, 'non-member');
          }
        }
      }

      generalObserver.observe(document.body, config);
    }

    function getCartDetails() {
      let totalQtn = 0,
        totalAdults = 0,
        totalChildren = 0,
        totalOriginalPrice = 0;

      $('.ts-cost-calculator .ts-item-name p')
        .not('.ts-total .ts-item-name p')
        .each(function () {
          let itemNameText = $(this).text();
          let quantityPattern = /(.+?)\s*×\s*(\d+)/;
          let match = itemNameText.match(quantityPattern);
          let quantity = match ? parseInt(match[2]) : 1;

          let priceElement = $(this).closest('li').find('.ts-item-price p');
          let originalPrice = parseFloat(
            priceElement.text().replace(/[^0-9.-]+/g, ''),
          );
          totalOriginalPrice += originalPrice;

          const typeArr = ['adults', 'children'];
          if (match) {
            let itemType = match[1].toLowerCase();
            if (itemType.includes('adult')) totalAdults += quantity;
            if (itemType.includes('child')) totalChildren += quantity;
          }

          totalQtn += quantity;
        });

      console.log({
        'Total Price': totalOriginalPrice,
        'Total Adults': totalAdults,
        'Total Children': totalChildren,
        'Total Quantity': totalQtn,
      });

      return {
        totalOriginalPrice,
        totalQtn,
        totalAdults,
        totalChildren,
      };
    }

    function calculateMemberRates(productDetails, membership_level) {
      var everythingCostaRicaAdultRate = 0;
      var everythingCostaRicaChildRate = 0;
      var totalAdultCost = 0;
      var totalChildCost = 0;
      var savedAmount = 0;

      let {
        post_id,
        adult_price,
        child_price,
        commission_discount,
        net_rate_adult,
        net_rate_child,
        adult_quantity,
        child_quantity,
        membership_adult_quantity,
        membership_child_quantity,
      } = productDetails;

      // Convert values to numbers (to prevent string issues)

      console.log('memberrates', productDetails);
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
      let commission_percentage = commission_discount / 100;

      if (adult_quantity > 0) {
        // // Calculate Vendor Profit (same as Net Rate)
        // let vendorProfitAdult = net_rate_adult;
        // let ourCommissionAdult = adult_price - vendorProfitAdult;

        // // Calculate everything_costa_rica_rates (Applying 25% Reduction in Commission)
        // let finalCommissionAdult =
        //   ourCommissionAdult - ourCommissionAdult * commission_percentage;

        // everythingCostaRicaAdultRate = net_rate_adult + finalCommissionAdult;
        // // Total cost for all adults
        // totalAdultCost = everythingCostaRicaAdultRate * adult_quantity;
        // 1. Calculate Costa Rica Adult Rate and it's commission
        let costaRicaAdultRate =
          adult_price - adult_price * commission_percentage;
        let costaRichaAdultCommission = adult_price - costaRicaAdultRate;

        // 2. Calculate extra adult quantity
        let extraAdult =
          membership_adult_quantity > adult_quantity
            ? 0
            : adult_quantity - membership_adult_quantity;

        // 3. Calculate total adult cost
        var totalAdultCost = adult_quantity * costaRicaAdultRate;
        var totalExtraAdultCommission =
          extraAdult * costaRichaAdultCommission ?? 0;

        // 4. Calculate everything costa rica adult rate
        everythingCostaRicaAdultRate = totalAdultCost;
      }

      if (child_quantity > 0) {
        // console.log('Calculated child Rates:', child_quantity);
        // // Calculate Vendor Profit (same as Net Rate)
        // let vendorProfitChild = net_rate_child;
        // let ourCommissionChild = child_price - vendorProfitChild;

        // // Calculate everything_costa_rica_rates (Applying 25% Reduction in Commission)
        // let finalCommissionChild =
        //   ourCommissionChild - ourCommissionChild * commission_percentage;

        // everythingCostaRicaChildRate = net_rate_child + finalCommissionChild;
        // // Total cost for all adults
        // totalChildCost = everythingCostaRicaChildRate * child_quantity;

        // 1. Calculate Costa Rica Child Rate and it's commission
        let costaRicaChildRate =
          child_price - child_price * commission_percentage;
        let costaRichaChildCommission = child_price - costaRicaChildRate;

        // 2. Calculate extra child quantity
        let extraChild =
          membership_child_quantity > child_quantity
            ? 0
            : child_quantity - membership_child_quantity;

        // 3. Calculate total child cost
        var totalChildCost = child_quantity * costaRicaChildRate;
        var totalExtraChildCommission =
          extraChild * costaRichaChildCommission ?? 0;
        // 4. Calculate everything costa rica child rate
        everythingCostaRicaChildRate = totalChildCost;
      }

      // Calculate deposit_amount (Applying Membership Discount to Only Membership Quantities)
      // let depositAmount =
      //   (everythingCostaRicaAdultRate - net_rate_adult) * adult_quantity -
      //   (everythingCostaRicaAdultRate - net_rate_adult) *
      //     membership_adult_quantity *
      //     membership_level +
      //   (everythingCostaRicaChildRate - net_rate_child) * child_quantity -
      //   (everythingCostaRicaChildRate - net_rate_child) *
      //     membership_child_quantity *
      //     membership_level;

      // deposit amount is our commission on extra adult and child
      let depositAmount = totalExtraAdultCommission + totalExtraChildCommission;

      // Ensure deposit_amount is never negative
      // if (child_quantity === 0) {
      //   depositAmount = Math.max(
      //     0,
      //     (everythingCostaRicaAdultRate - net_rate_adult) * adult_quantity -
      //       (everythingCostaRicaAdultRate - net_rate_adult) *
      //         membership_adult_quantity *
      //         membership_level,
      //   );
      // } else {
      //   depositAmount = Math.max(0, depositAmount);
      // }

      // savedAmount =
      //   (everythingCostaRicaAdultRate - net_rate_adult) * adult_quantity +
      //   (everythingCostaRicaChildRate - net_rate_child) * child_quantity;

      savedAmount =
        adult_price * adult_quantity +
        child_price * child_quantity -
        (totalAdultCost + totalChildCost);

      // Calculate Due on Arrival Amount (Same as Before)
      let dueOnArrival = totalAdultCost + totalChildCost;
      let totalCostaRicaRate = totalAdultCost + totalChildCost;

      let rates = {
        everything_costa_rica_adult_rate: everythingCostaRicaAdultRate,
        everything_costa_rica_child_rate: everythingCostaRicaChildRate,
        totalCostaRicaRate: totalCostaRicaRate,
        deposit_amount: depositAmount ?? 0,
        due_on_arrival: dueOnArrival,
        saved_amount: savedAmount,
      };

      console.log('Calculated Rates:', rates);
      return rates;
    }

    function calculateNonMemberRates(productDetails) {
      var everythingCostaRicaAdultRate = 0;
      var everythingCostaRicaChildRate = 0;
      var totalAdultCost = 0;
      var totalChildCost = 0;
      var savedAmount = 0;

      let {
        post_id,
        adult_price,
        child_price,
        commission_discount,
        net_rate_adult,
        net_rate_child,
        adult_quantity,
        child_quantity,
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
      let commission_percentage = commission_discount / 100;

      if (adult_quantity > 0) {
        let vendorProfitAdult = net_rate_adult;
        let ourCommissionAdult = adult_price - vendorProfitAdult;

        // Calculate everything_costa_rica_rates (Applying 25% Reduction in Commission)
        let finalCommissionAdult =
          ourCommissionAdult - ourCommissionAdult * 0.25;

        everythingCostaRicaAdultRate = net_rate_adult + finalCommissionAdult;
        // Total cost for all adults
        totalAdultCost = everythingCostaRicaAdultRate * adult_quantity;
      }

      if (child_quantity > 0) {
        let vendorProfitChild = net_rate_child;
        let ourCommissionChild = child_price - vendorProfitChild;

        // Calculate everything_costa_rica_rates (Applying 25% Reduction in Commission)
        let finalCommissionChild =
          ourCommissionChild - ourCommissionChild * commission_percentage;

        everythingCostaRicaChildRate = net_rate_child + finalCommissionChild;

        // Total cost for all adults
        totalChildCost = everythingCostaRicaChildRate * child_quantity;
      }

      // Correct deposit_amount Calculation
      let depositAmount =
        (everythingCostaRicaAdultRate - net_rate_adult) * adult_quantity +
        (everythingCostaRicaChildRate - net_rate_child) * child_quantity;

      let dueOnArrival =
        net_rate_adult * adult_quantity + net_rate_child * child_quantity;
      let totalCostaRicaRate = totalAdultCost + totalChildCost;

      let rates = {
        everything_costa_rica_adult_rate: everythingCostaRicaAdultRate,
        everything_costa_rica_child_rate: everythingCostaRicaChildRate,
        totalCostaRicaRate: totalCostaRicaRate,
        deposit_amount: depositAmount,
        due_on_arrival: dueOnArrival,
        saved_amount: savedAmount,
      };

      console.log('Calculated Non-Member Rates:', rates);
      return rates;
    }

    function updatePricingUI(rates, totalOriginalPrice, member) {
      console.log('totalCostaRicaRate', rates['totalCostaRicaRate']);
      console.log('due_on_arrival--', rates['due_on_arrival']);
      console.log(' update UI Rates:', rates);

      if (member == 'extra-member') {
        let upgrade_amount = rates['deposit_amount'] + rates['saved_amount'];
        $('.membership-banner .membership-msg').text(
          'You saved $' +
            rates['saved_amount'].toFixed(2) +
            ' because of your Membership - Upgrade to save up to $' +
            upgrade_amount.toFixed(2) +
            '.',
        );
      } else if (member == 'non-member') {
        $('.membership-banner .membership-msg').text(
          'Upgrade to save up to $' + rates['deposit_amount'].toFixed(2) + '.',
        );
      } else if (member == 'membership') {
        $('.membership-banner .membership-msg').text(
          'You saved $' +
            rates['saved_amount'].toFixed(2) +
            ' because of your Membership.',
        );
      }

      $('.membership-banner').css({
        background: 'green',
        display: 'block',
      });

      $('.ts-item-price.put-value').text('$' + totalOriginalPrice.toFixed(2));
      $('.ts-item-price.after-discount-price').text(
        '$' + rates['totalCostaRicaRate'].toFixed(2),
      );
      $('.ts-item-price.due-today').text(
        '$' + rates['deposit_amount'].toFixed(2),
      );
      $('.ts-item-price.due-arrival').text(
        '$' + rates['due_on_arrival'].toFixed(2),
      );
      $('.ts-item-price.deposit-amount').text(
        '$' + rates['due_on_arrival'].toFixed(2),
      );
      console.log('Updated Pricing UI with:', rates);
    }

    function nonLoggedUserDetails(cartDetails, productDetails) {
      var adult_quantity, child_quantity, rates, totalOriginalPrice;
      console.log(cartDetails);
      console.log(productDetails);
      adult_quantity = cartDetails.totalAdults;
      child_quantity = cartDetails.totalChildren;
      totalOriginalPrice = cartDetails.totalOriginalPrice;

      let membershipDetails = {
        ...productDetails,
        adult_quantity: adult_quantity,
        child_quantity: child_quantity,
        membership_adult_quantity: adult_quantity,
        membership_child_quantity: child_quantity,
      };

      rates = calculateMemberRates(membershipDetails, 2);
      console.log(rates);

      if (
        typeof rates['saved_amount'] === 'number' &&
        rates['saved_amount'] > 0
      ) {
        $('.membership-banner .membership-msg').text(
          'Become a member and get up to $' +
            rates['saved_amount'].toFixed(2) +
            ' off this purchase',
        );

        $('.membership-banner').css({
          background: 'green',
          display: 'block',
        });
      } else {
        console.log('remove banner');
        $('.membership-banner').css({
          display: 'none',
        });
      }
    }
  });
})(jQuery);
