!(function (e) {
    "function" == typeof define && define.amd ? define("planEditor", e) : e();
})(function () {
    "use strict";
    jQuery((e) => {
        var i = document.getElementById("vx-edit-plan");
        console.log('Child theme js');
        i &&
            ((window.$ = jQuery),
            (i = ((e) => {
                let i = JSON.parse(e.dataset.config);
                return Vue.createApp({
                    el: e,
                    data() {
                        return {
                            plan: i.plan,
                            postTypes: i.postTypes,
                            tab: "general",
                            subtab: "general",
                            loading: !1,
                            showArchive: !1,
                            mode: null,
                            createPrice: { live: null, test: null },
                            priceDefaults: { amount: null, currency: null, type: "recurring", interval: "month", intervalCount: 1, includeTax: !0 },
                            activePrice: null,
                            showPriceAdvanced: !1,
                            priceSetup: { live: null, liveError: null, test: null, testError: null },
                            limits: { activePostType: null, activePrice: null, activeTab: "main" },
                        };
                    },
                    created() {
                        ((window.Plan_Editor = this).createPrice.live = jQuery.extend(!0, {}, this.priceDefaults)), (this.createPrice.test = jQuery.extend(!0, {}, this.priceDefaults));
                        var e = Voxel_Backend.getSearchParam("tab");
                        e && ((e = e.split(".")), this.setTab(e[0], e[1]));
                    },
                    methods: {
                        setTab(e, i = "") {
                            (this.tab = e), (this.subtab = i);
                            var t = new URL(window.location);
                            t.searchParams.set("tab", i ? e + "." + i : e), window.history.replaceState(null, null, t), "pricing" === e && (this.mode = i);
                        },
                        addSubmission(e) {
                            this.postTypes[e] && (this.plan.submissions[e] = { count: 1, count_mode: "submitted_posts", count_mode_custom: [], relist_behavior: "same_slot", price_per_addition: { live: {}, test: {} } });
                        },
                        toggleSubmission(e) {
                            this.limits.activePostType === e ? (this.limits.activePostType = null) : ((this.limits.activePostType = e), (this.limits.activeTab = "main"));
                        },
                        save() {
                            this.loading = true;
                            var e = Voxel_Config.ajax_url + "&action=membership.update_plan";
                        
                            // 🔍 Debugging: Log Data Before Sending
                            console.log("Sending Data to Server:", JSON.stringify(this.plan, null, 2));
                        
                            jQuery.post(e, { plan: JSON.stringify(this.plan) })
                                .done(response => {
                                    this.loading = false;
                                    
                                    // 🔍 Debugging: Log the full server response
                                    console.log("Server Response:", response);
                        
                                    if (response && response.success) {
                                        console.log("Plan Updated Successfully!");
                                        location.reload(); // Reload if successful
                                    } else {
                                        alert("Server Error: " + (response.errors ? response.errors.join("\n - ") : "Unknown error"));
                                    }
                                })
                                .fail((xhr, status, error) => {
                                    this.loading = false;
                                    console.error("AJAX Request Failed:", status, error, xhr.responseText);
                                    alert("AJAX Error: " + error + "\nServer Response: " + xhr.responseText);
                                });
                        },
                        
                        archivePlan() {
                            var e;
                            (this.plan.archived || confirm("This plan will no longer be available to new users. Do you want to continue?")) &&
                                ((this.loading = !0),
                                (e = Voxel_Config.ajax_url + "&action=membership.archive_plan"),
                                jQuery.post(e, { plan: this.plan }, (e) => {
                                    (this.loading = !1), e.success ? (this.plan.archived = !this.plan.archived) : alert("Error:\n - " + e.errors.join("\n - "));
                                }));
                        },
                        deletePlan() {
                            var e;
                            confirm("This will permanently delete this plan. Do you want to continue?") &&
                                ((this.loading = !0),
                                (e = Voxel_Config.ajax_url + "&action=membership.delete_plan"),
                                jQuery.post(e, { plan: this.plan }, (e) => {
                                    (this.loading = !1), e.success ? (location.href = e.redirect_to) : alert("Error:\n - " + e.errors.join("\n - "));
                                }));
                        },
                        insertPrice() {
                            this.loading = !0;
                            var e = Voxel_Config.ajax_url + "&action=membership.create_price";
                            jQuery.post(e, { plan: this.plan.key, mode: this.mode, price: this.createPrice[this.mode] }, (e) => {
                                (this.loading = !1),
                                    e.success ? ((this.plan.pricing[this.mode] = e.pricing[this.mode]), (this.createPrice[this.mode] = jQuery.extend(!0, {}, this.priceDefaults))) : alert("Error:\n - " + e.errors.join("\n - "));
                            });
                        },
                        stripeProductUrl() {
                            return "live" === this.mode ? "https://dashboard.stripe.com/products/" + this.plan.pricing.live.product_id : "https://dashboard.stripe.com/test/products/" + this.plan.pricing.test.product_id;
                        },
                        stripePriceUrl(e) {
                            return "live" === this.mode ? "https://dashboard.stripe.com/prices/" + e : "https://dashboard.stripe.com/test/prices/" + e;
                        },
                        syncPrices() {
                            this.loading = !0;
                            var e = Voxel_Config.ajax_url + "&action=membership.sync_prices";
                            jQuery.get(e, { plan: this.plan.key, mode: this.mode }, (e) => {
                                (this.loading = !1), e.success ? (this.plan.pricing[this.mode] = e.pricing[this.mode]) : alert("Error:\n - " + e.errors.join("\n - "));
                            });
                        },
                        togglePrice(e) {
                            this.loading = !0;
                            var i = Voxel_Config.ajax_url + "&action=membership.toggle_price";
                            jQuery.get(i, { plan: this.plan.key, mode: this.mode, price: e }, (e) => {
                                (this.loading = !1), e.success ? (this.plan.pricing[this.mode] = e.pricing[this.mode]) : alert("Error:\n - " + e.errors.join("\n - "));
                            });
                        },
                        getPriceLabel(e) {
                            var i = (e.is_zero_decimal ? e.amount : e.amount / 100).toLocaleString();
                            return e.currency.toUpperCase() + " " + i;
                        },
                        getPricePeriod(e) {
                            return "recurring" === e.type ? `every ${e.recurring.interval_count} ${e.recurring.interval}s` : "one time";
                        },
                        addPricePerAddition(e, i, t) {
                            var r = this.getPriceById(e.target.value, t);
                            r && (i.price_per_addition[t][r.id] = { amount: "" }), (e.target.value = "");
                        },
                        getPriceById(i, e) {
                            return this.plan.pricing[e]?.prices.find((e) => e.id === i);
                        },
                    },
                    watch: {
                        mode() {
                            let i = this.mode;
                            var e;
                            null === this.priceSetup[i] &&
                                ((e = Voxel_Config.ajax_url + "&action=membership.setup_pricing"),
                                jQuery.get(e, { plan: this.plan.key, mode: i }, (e) => {
                                    (this.loading = !1), e.success ? ((this.plan.pricing[i].product_id = e.product_id), (this.priceSetup[i] = !0)) : ((this.priceSetup[i] = !1), (this.priceSetup[i + "Error"] = e.message));
                                }));
                        },
                    },
                });
            })(i)).component("field-key", Voxel_Backend.components.Field_Key),
            i.mount("#vx-edit-plan"));
    });
});
