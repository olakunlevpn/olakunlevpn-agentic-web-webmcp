/**
 * [Olakunlevpn] Agentic Web & WebMCP Bridge
 * Exposes WebMCP tools to autonomous AI agents, enables declarative form annotations,
 * and auto-enriches machine-eye accessibility (a11y) tree.
 */
(function(window, document) {
    'use strict';

    var AgenticWeb = {
        config: window.__AGENTIC_WEB_CONFIG__ || {
            enabled: true,
            mcpEnabled: true,
            apiEndpoint: '/webmcp',
            ardEndpoint: '/ard-json',
            a11yLabels: true,
            a11yLive: true
        },

        init: function() {
            if (!this.config.enabled) return;

            if (this.config.mcpEnabled) {
                this.initWebMCP();
            }

            if (this.config.a11yLabels || this.config.a11yLive) {
                this.initA11yShield();
                this.initObserver();
            }

            this.annotateDeclarativeForms();

            // Re-apply on dynamic AJAX content loads
            var self = this;
            var reapply = function() {
                if (self.config.a11yLabels || self.config.a11yLive) {
                    self.initA11yShield();
                }
                self.annotateDeclarativeForms();
            };

            document.addEventListener('xf:page-load-complete', reapply);
            document.addEventListener('xf:layout-change', reapply);
            document.addEventListener('xf:reinit', reapply);
        },

        initObserver: function() {
            if (!window.MutationObserver) return;

            var sanitizeNode = function(node) {
                if (!node || node.nodeType !== 1) return;

                var tag = (node.tagName || '').toUpperCase();

                // Prohibited role="tabpanel" and aria-expanded on <li>
                if (tag === 'LI') {
                    if (node.getAttribute('role') === 'tabpanel') {
                        node.removeAttribute('role');
                        var child = node.querySelector('div');
                        if (child && !child.getAttribute('role')) {
                            child.setAttribute('role', 'tabpanel');
                            if (!child.getAttribute('aria-label') && !child.getAttribute('aria-labelledby')) {
                                child.setAttribute('aria-label', (node.id ? node.id.replace(/[-_]/g, ' ') : 'Tab panel'));
                            }
                        }
                    }
                    if (node.hasAttribute('aria-expanded')) {
                        node.removeAttribute('aria-expanded');
                    }
                }

                // Prohibited role="tablist" on headings <h1>-<h6>
                if (/^H[1-6]$/.test(tag) && node.getAttribute('role') === 'tablist') {
                    var div = document.createElement('div');
                    for (var a = 0; a < node.attributes.length; a++) {
                        div.setAttribute(node.attributes[a].name, node.attributes[a].value);
                    }
                    while (node.firstChild) {
                        div.appendChild(node.firstChild);
                    }
                    if (node.parentNode) {
                        node.parentNode.replaceChild(div, node);
                    }
                    return;
                }

                // Prohibited focusable elements inside aria-hidden
                if (node.getAttribute('aria-hidden') === 'true' && (tag === 'A' || tag === 'BUTTON' || tag === 'INPUT')) {
                    if (node.getAttribute('tabindex') !== '-1') {
                        node.setAttribute('tabindex', '-1');
                    }
                }

                // Deep scan subtrees
                if (node.querySelectorAll) {
                    var lis = node.querySelectorAll('li[role="tabpanel"], li[aria-expanded]');
                    for (var l = 0; l < lis.length; l++) {
                        var li = lis[l];
                        if (li.getAttribute('role') === 'tabpanel') {
                            li.removeAttribute('role');
                            var c = li.querySelector('div');
                            if (c && !c.getAttribute('role')) {
                                c.setAttribute('role', 'tabpanel');
                                if (!c.getAttribute('aria-label') && !c.getAttribute('aria-labelledby')) {
                                    c.setAttribute('aria-label', (li.id ? li.id.replace(/[-_]/g, ' ') : 'Tab panel'));
                                }
                            }
                        }
                        if (li.hasAttribute('aria-expanded')) {
                            li.removeAttribute('aria-expanded');
                        }
                    }

                    var headings = node.querySelectorAll('h1[role="tablist"], h2[role="tablist"], h3[role="tablist"], h4[role="tablist"], h5[role="tablist"], h6[role="tablist"]');
                    for (var h = 0; h < headings.length; h++) {
                        var hd = headings[h];
                        var replacement = document.createElement('div');
                        for (var att = 0; att < hd.attributes.length; att++) {
                            replacement.setAttribute(hd.attributes[att].name, hd.attributes[att].value);
                        }
                        while (hd.firstChild) {
                            replacement.appendChild(hd.firstChild);
                        }
                        if (hd.parentNode) {
                            hd.parentNode.replaceChild(replacement, hd);
                        }
                    }

                    var hiddenLinks = node.querySelectorAll('a[aria-hidden="true"]:not([tabindex="-1"])');
                    for (var hl = 0; hl < hiddenLinks.length; hl++) {
                        hiddenLinks[hl].setAttribute('tabindex', '-1');
                    }
                }
            };

            var a11yObserver = new MutationObserver(function(mutations) {
                for (var m = 0; m < mutations.length; m++) {
                    var mut = mutations[m];
                    if (mut.type === 'attributes') {
                        sanitizeNode(mut.target);
                    } else if (mut.type === 'childList') {
                        for (var n = 0; n < mut.addedNodes.length; n++) {
                            sanitizeNode(mut.addedNodes[n]);
                        }
                    }
                }
            });

            a11yObserver.observe(document.documentElement, {
                subtree: true,
                childList: true,
                attributes: true,
                attributeFilter: ['role', 'aria-expanded', 'aria-hidden']
            });
        },

        initWebMCP: function() {
            var apiBase = this.config.apiEndpoint;

            var tools = {
                searchForum: {
                    name: 'searchForum',
                    description: 'Search community discussions, threads, and market items by keyword.',
                    inputSchema: {
                        type: 'object',
                        properties: {
                            q: { type: 'string', description: 'Keywords to search' },
                            node_id: { type: 'number', description: 'Optional node ID filter' },
                            limit: { type: 'number', description: 'Max results (1-25)', default: 10 }
                        },
                        required: ['q']
                    },
                    execute: function(args) {
                        var params = new URLSearchParams(args);
                        return fetch(apiBase + '/search?' + params.toString(), {
                            headers: { 'Accept': 'application/json' }
                        }).then(function(r) { return r.json(); });
                    }
                },

                getThread: {
                    name: 'getThread',
                    description: 'Fetch clean, structured thread message content and initial replies by thread ID.',
                    inputSchema: {
                        type: 'object',
                        properties: {
                            thread_id: { type: 'number', description: 'Target thread ID' },
                            page: { type: 'number', description: 'Page number', default: 1 }
                        },
                        required: ['thread_id']
                    },
                    execute: function(args) {
                        var params = new URLSearchParams(args);
                        return fetch(apiBase + '/thread?' + params.toString(), {
                            headers: { 'Accept': 'application/json' }
                        }).then(function(r) { return r.json(); });
                    }
                },

                getNodeList: {
                    name: 'getNodeList',
                    description: 'Retrieve public forum categories, boards, and discussion counts.',
                    inputSchema: { type: 'object', properties: {} },
                    execute: function() {
                        return fetch(apiBase + '/nodes', {
                            headers: { 'Accept': 'application/json' }
                        }).then(function(r) { return r.json(); });
                    }
                },

                getRecentDiscussions: {
                    name: 'getRecentDiscussions',
                    description: 'Retrieve latest active community discussions and recent replies.',
                    inputSchema: {
                        type: 'object',
                        properties: {
                            limit: { type: 'number', description: 'Max topics (1-25)', default: 10 }
                        }
                    },
                    execute: function(args) {
                        var params = new URLSearchParams(args || {});
                        return fetch(apiBase + '/recent?' + params.toString(), {
                            headers: { 'Accept': 'application/json' }
                        }).then(function(r) { return r.json(); });
                    }
                }
            };

            // Register with standard W3C navigator.modelContext if supported by the browser agent (Chrome 150+ / WebMCP)
            if (typeof navigator !== 'undefined' && navigator.modelContext && typeof navigator.modelContext.registerTool === 'function') {
                for (var toolKey in tools) {
                    if (tools.hasOwnProperty(toolKey)) {
                        try {
                            navigator.modelContext.registerTool(tools[toolKey]);
                        } catch (e) {
                            console.warn('[WebMCP] Failed to register tool with navigator.modelContext', e);
                        }
                    }
                }
            }

            // Expose global window.__WEBMCP_TOOLS__ and window.WebMCP for script-driven agents and extensions
            window.__WEBMCP_TOOLS__ = tools;
            window.WebMCP = {
                version: '1.0.3',
                tools: tools,
                call: function(toolName, args) {
                    if (tools[toolName] && typeof tools[toolName].execute === 'function') {
                        return tools[toolName].execute(args);
                    }
                    return Promise.reject(new Error('Tool not found: ' + toolName));
                }
            };

            try {
                window.dispatchEvent(new CustomEvent('webmcp:ready', { detail: { tools: tools } }));
            } catch (e) {}
        },

        initA11yShield: function() {
            var applyA11y = function() {
                // 1. ARIA role appropriate for element: Convert heading elements carrying role="tablist" to div
                var invalidHeadingTabs = document.querySelectorAll('h1[role="tablist"], h2[role="tablist"], h3[role="tablist"], h4[role="tablist"], h5[role="tablist"], h6[role="tablist"]');
                for (var ht = 0; ht < invalidHeadingTabs.length; ht++) {
                    var hEl = invalidHeadingTabs[ht];
                    var divEl = document.createElement('div');
                    for (var att = 0; att < hEl.attributes.length; att++) {
                        divEl.setAttribute(hEl.attributes[att].name, hEl.attributes[att].value);
                    }
                    while (hEl.firstChild) {
                        divEl.appendChild(hEl.firstChild);
                    }
                    hEl.parentNode.replaceChild(divEl, hEl);
                }

                // 2. ARIA role appropriate for element: Disallow prohibited role="tabpanel" and aria-expanded on <li> elements
                var invalidLiPanels = document.querySelectorAll('li[role="tabpanel"], li[aria-expanded]');
                for (var lp = 0; lp < invalidLiPanels.length; lp++) {
                    var li = invalidLiPanels[lp];
                    if (li.getAttribute('role') === 'tabpanel') {
                        li.removeAttribute('role');
                        var childDiv = li.querySelector('div');
                        if (childDiv && !childDiv.getAttribute('role')) {
                            childDiv.setAttribute('role', 'tabpanel');
                            if (!childDiv.getAttribute('aria-label') && !childDiv.getAttribute('aria-labelledby')) {
                                childDiv.setAttribute('aria-label', (li.id ? li.id.replace(/[-_]/g, ' ') : 'Tab panel'));
                            }
                        }
                    }
                    if (li.hasAttribute('aria-expanded')) {
                        li.removeAttribute('aria-expanded');
                    }
                }

                // 3. ARIA commands must have an accessible name: Ensure nav menu triggers & role="button" have accessible name
                var cmdButtons = document.querySelectorAll('.p-nav-menuTrigger, [data-xf-click="off-canvas"], [role="button"]:not([aria-label])');
                for (var cb = 0; cb < cmdButtons.length; cb++) {
                    var cmd = cmdButtons[cb];
                    if (!cmd.getAttribute('aria-label')) {
                        var accessibleText = (cmd.innerText || cmd.textContent || '').trim();
                        if (accessibleText === '') {
                            var label = cmd.getAttribute('title') || cmd.getAttribute('data-original-title') || 'Navigation Menu';
                            cmd.setAttribute('aria-label', label);
                        }
                    }
                }

                // 4. ARIA hidden elements must NOT be focusable: Ensure aria-hidden links/inputs have tabindex="-1"
                var hiddenFocusables = document.querySelectorAll('a[aria-hidden="true"], button[aria-hidden="true"], input[aria-hidden="true"], select[aria-hidden="true"], textarea[aria-hidden="true"], [aria-hidden="true"] a, [aria-hidden="true"] button, [aria-hidden="true"] input');
                for (var hf = 0; hf < hiddenFocusables.length; hf++) {
                    hiddenFocusables[hf].setAttribute('tabindex', '-1');
                }

                // 5. Links must have discernible text: Ensure image-only or icon-only links have aria-label / alt text
                var allLinks = document.querySelectorAll('a');
                for (var al = 0; al < allLinks.length; al++) {
                    var aLink = allLinks[al];
                    var linkText = (aLink.textContent || '').trim();
                    if (linkText === '' && !aLink.getAttribute('aria-label')) {
                        var childImg = aLink.querySelector('img');
                        if (childImg) {
                            var imgAlt = (childImg.getAttribute('alt') || '').trim();
                            if (!imgAlt) {
                                imgAlt = aLink.getAttribute('title') || 'Sponsored Advertisement';
                                childImg.setAttribute('alt', imgAlt);
                            }
                            aLink.setAttribute('aria-label', imgAlt);
                        } else {
                            var icon = aLink.querySelector('i, svg');
                            if (icon) {
                                var cls = (icon.getAttribute('class') || '') + ' ' + (icon.getAttribute('data-icon') || '');
                                var iconTitle = aLink.getAttribute('title') || aLink.getAttribute('data-original-title');
                                if (!iconTitle) {
                                    if (cls.indexOf('fa-search') !== -1) iconTitle = 'Search';
                                    else if (cls.indexOf('fa-bookmark') !== -1) iconTitle = 'Bookmark';
                                    else if (cls.indexOf('fa-bell') !== -1) iconTitle = 'Alerts & Notifications';
                                    else if (cls.indexOf('fa-envelope') !== -1) iconTitle = 'Conversations';
                                    else if (cls.indexOf('fa-user') !== -1) iconTitle = 'User account';
                                    else if (cls.indexOf('fa-bars') !== -1) iconTitle = 'Navigation menu';
                                }
                                if (iconTitle) {
                                    aLink.setAttribute('aria-label', iconTitle);
                                }
                            }
                        }
                    }
                }

                // 6. Ensure form inputs have an accessible name
                var orphanInputs = document.querySelectorAll('input:not([type="hidden"]):not([aria-label]):not([aria-labelledby]), select:not([aria-label]):not([aria-labelledby]), textarea:not([aria-label]):not([aria-labelledby])');
                for (var k = 0; k < orphanInputs.length; k++) {
                    var inp = orphanInputs[k];
                    var labelText = inp.getAttribute('placeholder') || inp.getAttribute('title');
                    if (labelText) {
                        inp.setAttribute('aria-label', labelText);
                    }
                }

                // 7. Add aria-live="polite" and role="region" to real-time dynamic statistics blocks
                var liveStats = document.querySelectorAll('.dcom-live-statistic, .block--liveStatistic, .p-navgroup-link--alerts, [data-xf-init*="live-statistic"]');
                for (var j = 0; j < liveStats.length; j++) {
                    if (!liveStats[j].hasAttribute('aria-live')) {
                        liveStats[j].setAttribute('aria-live', 'polite');
                        liveStats[j].setAttribute('aria-atomic', 'true');
                        liveStats[j].setAttribute('role', 'region');
                        if (!liveStats[j].hasAttribute('aria-label')) {
                            liveStats[j].setAttribute('aria-label', 'Live Community Activity');
                        }
                    }
                }
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', applyA11y);
            } else {
                applyA11y();
            }
        },

        annotateDeclarativeForms: function() {
            var annotate = function() {
                var allForms = document.querySelectorAll('form');
                for (var i = 0; i < allForms.length; i++) {
                    var form = allForms[i];
                    var action = form.getAttribute('action') || '';

                    // 1. Identify primary form functions and assign toolname/tooldescription
                    if (action.indexOf('search') !== -1 || form.getAttribute('data-xf-init') === 'quick-search') {
                        if (!form.hasAttribute('toolname')) {
                            form.setAttribute('toolname', 'search_forum');
                        }
                        if (!form.hasAttribute('tooldescription')) {
                            form.setAttribute('tooldescription', 'Search forum discussions, topics, marketplace listings, and member posts');
                        }
                    } else if (action.indexOf('login') !== -1) {
                        if (!form.hasAttribute('toolname')) {
                            form.setAttribute('toolname', 'user_login');
                        }
                        if (!form.hasAttribute('tooldescription')) {
                            form.setAttribute('tooldescription', 'Authenticate member account with username and password');
                        }
                    }

                    // 2. Strict Symmetry Check (Required by Lighthouse webmcp-schema-validity)
                    var toolName = form.getAttribute('toolname');
                    var toolDesc = form.getAttribute('tooldescription');

                    if (toolName && !toolDesc) {
                        form.setAttribute('tooldescription', 'Execute action ' + toolName);
                    } else if (toolDesc && !toolName) {
                        form.setAttribute('toolname', form.id || 'form_action');
                    }

                    // 3. If form is an annotated WebMCP tool, strictly validate and annotate every input
                    if (form.hasAttribute('toolname')) {
                        var controls = form.querySelectorAll('input, select, textarea');
                        for (var c = 0; c < controls.length; c++) {
                            var control = controls[c];
                            var name = control.getAttribute('name');

                            // Ensure every input has a name attribute
                            if (!name) {
                                name = control.id || ('input_' + c);
                                control.setAttribute('name', name);
                            }

                            // Provide toolparamdescription
                            if (!control.hasAttribute('toolparamdescription')) {
                                var paramDesc = '';
                                if (name === 'keywords' || name === 'q') {
                                    paramDesc = 'Keywords or search terms to query';
                                } else if (name === 'login' || name === 'username') {
                                    paramDesc = 'Username or registered email address';
                                } else if (name === 'password') {
                                    paramDesc = 'Account password';
                                } else if (name === '_xfToken') {
                                    paramDesc = 'CSRF security token';
                                } else if (name === '_xfResponseType') {
                                    paramDesc = 'Response type identifier';
                                } else if (name.indexOf('title_only') !== -1) {
                                    paramDesc = 'Search in thread titles only';
                                } else if (name.indexOf('users') !== -1) {
                                    paramDesc = 'Filter search by author username';
                                } else if (name.indexOf('newer_than') !== -1) {
                                    paramDesc = 'Filter results newer than date';
                                } else if (name.indexOf('nodes') !== -1 || name === 'constraints') {
                                    paramDesc = 'Filter search by forum category';
                                } else if (control.type === 'submit' || control.type === 'button') {
                                    paramDesc = 'Action submit trigger';
                                } else {
                                    paramDesc = control.getAttribute('placeholder') || control.getAttribute('aria-label') || ('Parameter ' + name);
                                }

                                control.setAttribute('toolparamdescription', paramDesc);
                            }
                        }
                    }
                }
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', annotate);
            } else {
                annotate();
            }
        }
    };

    AgenticWeb.init();
})(window, document);
