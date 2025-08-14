# Membership Discount Logic Documentation

## Overview

This document describes the custom discount logic implemented in `voxel-child/js/custom.js` for handling membership-based pricing and discounts for Everything Costa Rica products.

## Core Components

### 1. Main Structure

The logic is wrapped in an IIFE (Immediately Invoked Function Expression) and uses jQuery's document ready function:

```javascript
(function ($) {
  'use strict';

  jQuery(document).ready(function ($) {
    // Main logic implementation
  });
})(jQuery);
```

### 2. Key Variables and Data Sources

#### User Membership Data

- `userMembership.member`: Current membership status
- `userMembership.productDetails`: Product pricing and membership information

#### Product Details Structure

```javascript
{
  post_id: number,
  adult_price: number,
  child_price: number,
  commission_discount: number,
  net_rate_adult: number,
  net_rate_child: number,
  membership_adult_quantity: number,
  membership_child_quantity: number,
  membership_level: number
}
```

## Discount Logic Flow

### 1. User Status Detection

The system first determines the user's login and membership status:

```javascript
var member = userMembership.member;

if (
  member === '' ||
  member === 'undefined' ||
  member === 0 ||
  member === 'null'
) {
  // Non-logged in user
  nonLoggedUserDetails(cartDetails, productDetails);
} else {
  // Logged in user - check membership type
  if (member !== 'default') {
    // Member with active membership
    // Apply membership discounts
  } else {
    // Default member (no active membership)
    // Apply non-member rates
  }
}
```

### 2. Cart Quantity Validation

The system validates that cart quantities don't exceed membership limits:

```javascript
if (
  adult_quantity <= membership_adult_quantity &&
  child_quantity <= membership_child_quantity
) {
  // Apply full membership discount
  calculatedRates = calculateMemberRates(
    membershipDetails,
    productDetails.membership_level,
  );
} else {
  // Apply partial membership discount + extra charges
  // Calculate extra quantities beyond membership limits
}
```

## Calculation Methods

### 1. Member Rates Calculation (`calculateMemberRates`)

#### Formula Breakdown:

1. **Commission Calculation**:

   ```javascript
   let vendorProfitAdult = net_rate_adult;
   let ourCommissionAdult = adult_price - vendorProfitAdult;
   let finalCommissionAdult =
     ourCommissionAdult - ourCommissionAdult * commission_percentage;
   ```

2. **Everything Costa Rica Rate**:

   ```javascript
   everythingCostaRicaAdultRate = net_rate_adult + finalCommissionAdult;
   ```

3. **Deposit Amount Calculation**:

   ```javascript
   let depositAmount =
     (everythingCostaRicaAdultRate - net_rate_adult) * adult_quantity -
     (everythingCostaRicaAdultRate - net_rate_adult) *
       membership_adult_quantity *
       membership_level +
     (everythingCostaRicaChildRate - net_rate_child) * child_quantity -
     (everythingCostaRicaChildRate - net_rate_child) *
       membership_child_quantity *
       membership_level;
   ```

4. **Saved Amount Calculation**:
   ```javascript
   savedAmount =
     (everythingCostaRicaAdultRate - net_rate_adult) * adult_quantity +
     (everythingCostaRicaChildRate - net_rate_child) * child_quantity;
   ```

### 2. Non-Member Rates Calculation (`calculateNonMemberRates`)

Similar to member rates but without membership discounts:

```javascript
let depositAmount =
  (everythingCostaRicaAdultRate - net_rate_adult) * adult_quantity +
  (everythingCostaRicaChildRate - net_rate_child) * child_quantity;
```

### 3. Extra Member Logic (Beyond Membership Limits)

When quantities exceed membership limits:

```javascript
let extra_adults = Math.max(0, adult_quantity - membership_adult_quantity);
let extra_children = Math.max(0, child_quantity - membership_child_quantity);

// Calculate member rates for allowed quantities
let memberRates = calculateMemberRates(
  applyMembershipDetails,
  productDetails.membership_level,
);

// Calculate non-member rates for extra quantities
let nonMemberRates = calculateNonMemberRates(extraMemberDetails);

// Combine both rates
let finalRates = {};
Object.keys(memberRates).forEach((key) => {
  finalRates[key] = (memberRates[key] || 0) + (nonMemberRates[key] || 0);
});
```

## UI Update Logic

### 1. Banner Messages

Different banner messages based on user status:

- **Membership User**: "You saved $X because of your Membership."
- **Extra Member**: "You saved $X because of your Membership - Upgrade to save up to $Y."
- **Non-Member**: "Upgrade to save up to $X."
- **Non-Logged User**: "Become a member and get up to $X off this purchase"

### 2. Price Display Updates

```javascript
$('.ts-item-price.put-value').text('$' + totalOriginalPrice.toFixed(2));
$('.ts-item-price.after-discount-price').text(
  '$' + rates['totalCostaRicaRate'].toFixed(2),
);
$('.ts-item-price.due-today').text('$' + rates['deposit_amount'].toFixed(2));
$('.ts-item-price.due-arrival').text('$' + rates['due_on_arrival'].toFixed(2));
```

## Cart Details Extraction

The system extracts cart information using jQuery selectors:

```javascript
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

      // Parse adult/child quantities and prices
      let itemType = match[1].toLowerCase();
      if (itemType.includes('adult')) totalAdults += quantity;
      if (itemType.includes('child')) totalChildren += quantity;
    });

  return {
    totalOriginalPrice,
    totalQtn,
    totalAdults,
    totalChildren,
  };
}
```

## Mutation Observer Implementation

The system uses a MutationObserver to detect DOM changes and trigger price updates:

```javascript
var callback = function (mutationsList, observer) {
  for (let mutation of mutationsList) {
    if (mutation.type === 'childList') {
      applyUpdatePrice();
    }
  }
};

var generalObserver = new MutationObserver(callback);
generalObserver.observe(document.body, config);
```

## Key Functions Reference

### Main Functions:

1. `applyUpdatePrice()` - Main orchestrator function
2. `getCartDetails()` - Extracts cart quantities and prices
3. `calculateMemberRates()` - Calculates member pricing
4. `calculateNonMemberRates()` - Calculates non-member pricing
5. `updatePricingUI()` - Updates UI with calculated prices
6. `nonLoggedUserDetails()` - Handles non-logged user scenarios

### Helper Functions:

- Price formatting with `.toFixed(2)`
- Quantity validation and limits checking
- Commission percentage calculations
- Rate merging for mixed scenarios

## Error Handling

The system includes several validation checks:

- Product details availability
- Cart quantity validation
- Negative deposit amount prevention
- Zero quantity handling

## Browser Compatibility

- Uses jQuery for DOM manipulation
- ES6+ features (let/const, arrow functions, template literals)
- MutationObserver API for DOM monitoring
- Modern JavaScript features for calculations

## Performance Considerations

- MutationObserver disconnection/reconnection to prevent infinite loops
- Efficient DOM querying with jQuery selectors
- Minimal DOM updates with targeted element selection
- Console logging for debugging (should be removed in production)

## Maintenance Notes

1. **Commission Rates**: Currently hardcoded to 25% reduction in some places
2. **Membership Levels**: Supports different membership discount percentages
3. **Banner Styling**: Uses inline CSS for banner display/hide
4. **Price Elements**: Relies on specific CSS classes for price updates

## Future Enhancements

1. Move hardcoded values to configuration
2. Add more comprehensive error handling
3. Implement caching for repeated calculations
4. Add unit tests for calculation functions
5. Consider using a state management system for complex scenarios
