/**
 * Conversion deck controller.
 *
 * Everything here is progressive enhancement on top of a server-rendered
 * partial. The deck never issues a form POST, so opening, validating and
 * committing a lead produces no navigation event: the surrounding page keeps
 * its scroll position and DOM state throughout.
 */
(() => {
    "use strict";

    const deck = document.getElementById("funnel-deck");
    if (!deck) return;

    const overlay = document.getElementById("funnel-overlay");
    const alertBox = document.getElementById("funnel-alert");
    const stepLabel = document.getElementById("funnel-step-label");
    const steps = {
        1: document.getElementById("funnel-step-1"),
        2: document.getElementById("funnel-step-2"),
        3: document.getElementById("funnel-step-3"),
    };

    const toStep2 = document.getElementById("funnel-to-step-2");
    const backBtn = document.getElementById("funnel-back");
    const submitBtn = document.getElementById("funnel-submit");
    const closeBtn = document.getElementById("funnel-close");
    const doneBtn = document.getElementById("funnel-done");
    const calendarLink = document.getElementById("funnel-calendar");
    const step2Heading = document.getElementById("funnel-step-2-heading");
    const step2Body = document.getElementById("funnel-step-2-body");
    const emailLabel = document.getElementById("funnel-email-label");
    const emailHint = document.getElementById("funnel-email-hint");

    const validateUrl = deck.dataset.validateUrl;
    const submitUrl = deck.dataset.submitUrl;
    const csrfToken = deck.dataset.csrfToken;

    /**
     * The server's funnel configuration, read from the JSON block in base.html.
     * It decides which fields each track asks for; the markup for both tracks is
     * already present, so switching is a matter of revealing the right groups.
     */
    let config = {};
    try {
        const node = document.getElementById("funnel-config");
        if (node) config = JSON.parse(node.textContent || "{}");
    } catch {
        // A malformed config must not break the page; the deck then behaves as
        // the organisation track, which is the server's default.
        config = {};
    }

    /** Fields audited on blur. Organisation-only fields are added per track. */
    const BASE_AUDITED = ["fullName", "email", "phone"];

    /** Tracks the outcome of the last blur audit per field. */
    const fieldState = Object.create(null);

    let lastFocusedElement = null;
    let isOpen = false;

    // ---------------------------------------------------------------- helpers

    const fieldEl = (name) => deck.querySelector(`[data-funnel-field="${name}"]`);

    const groupEl = (name) => deck.querySelector(`[data-funnel-group="${name}"]`);

    const errorEl = (name) => {
        const group = groupEl(name);
        return group ? group.querySelector("[data-funnel-error]") : null;
    };

    /** The track currently selected, falling back to the server's default. */
    function currentTrack() {
        const slug = selectedTier();
        const fromMap = slug && config.tracks ? config.tracks[slug] : null;
        return fromMap || config.track || "organisation";
    }

    /** The fields the current track collects. */
    function auditedFields() {
        const track = currentTrack();
        const groups = track === "individual" ? [] : ["companyName", "department", "organizationScope"];
        return [...BASE_AUDITED, ...groups];
    }

    /**
     * Shows the field groups belonging to the selected track and hides the rest.
     *
     * Groups are matched on data-track-field; anything without the attribute is
     * shown for every track. Hidden inputs are disabled so the browser does not
     * submit a stale value the track never asked for.
     */
    function applyTrack() {
        const track = currentTrack();
        const copy = (config.copy && config.copy[track]) || {};

        deck.querySelectorAll("[data-track-field]").forEach((group) => {
            const belongs = group.dataset.trackField === track;
            group.classList.toggle("hidden", !belongs);
            group.querySelectorAll("input, select, textarea").forEach((input) => {
                input.disabled = !belongs;
            });
        });

        if (step2Heading && copy.heading) step2Heading.textContent = copy.heading;
        if (step2Body && copy.body) step2Body.textContent = copy.body;
        if (emailLabel && copy.emailLabel) emailLabel.textContent = copy.emailLabel;
        if (emailHint) emailHint.textContent = copy.emailHint || "";

        // A field hidden by a track change must not keep an error on screen.
        clearAllMarks();
    }

    /** Applies the crimson border + inline message for one field. */
    function markInvalid(name, message) {
        const input = fieldEl(name);
        const error = errorEl(name);
        fieldState[name] = false;

        if (input) {
            input.classList.remove("border-slate-800/20");
            input.classList.add("border-red-500");
            input.setAttribute("aria-invalid", "true");
        }
        if (error) {
            error.textContent = message;
            error.classList.remove("hidden");
        }
    }

    function markValid(name) {
        const input = fieldEl(name);
        const error = errorEl(name);
        fieldState[name] = true;

        if (input) {
            input.classList.remove("border-red-500");
            input.classList.add("border-slate-800/20");
            input.removeAttribute("aria-invalid");
        }
        if (error) {
            error.textContent = "";
            error.classList.add("hidden");
        }
    }

    function clearAllMarks() {
        ["fullName", "companyName", "department", "email", "phone", "organizationScope", "teamSize", "preferredChannel"]
            .forEach((name) => {
                markValid(name);
                fieldState[name] = undefined;
            });
    }

    function showAlert(message) {
        if (!alertBox) return;
        alertBox.textContent = message;
        alertBox.classList.remove("hidden");
    }

    function hideAlert() {
        if (!alertBox) return;
        alertBox.textContent = "";
        alertBox.classList.add("hidden");
    }

    function setStep(step) {
        Object.entries(steps).forEach(([key, el]) => {
            if (el) el.classList.toggle("hidden", Number(key) !== step);
        });

        if (stepLabel) {
            stepLabel.textContent =
                step === 1 ? "Step 1 of 2 · Select your objective"
                : step === 2 ? "Step 2 of 2 · Your details"
                : "Request confirmed";
        }
    }

    // ----------------------------------------------------------- open / close

    function openDeck() {
        lastFocusedElement = document.activeElement;
        isOpen = true;

        overlay?.classList.remove("hidden");
        overlay?.setAttribute("aria-hidden", "false");
        deck.classList.remove("translate-x-full");
        deck.setAttribute("aria-hidden", "false");

        // Lock the page behind the deck. Compensating for the scrollbar keeps
        // the layout from shifting horizontally as it disappears.
        const scrollbar = window.innerWidth - document.documentElement.clientWidth;
        document.body.style.overflow = "hidden";
        if (scrollbar > 0) document.body.style.paddingRight = `${scrollbar}px`;

        // Focus the first meaningful control once the slide-in has settled.
        window.setTimeout(() => {
            deck.querySelector('input[type="radio"]')?.focus();
        }, 300);
    }

    function closeDeck() {
        isOpen = false;
        deck.classList.add("translate-x-full");
        deck.setAttribute("aria-hidden", "true");
        overlay?.classList.add("hidden");
        overlay?.setAttribute("aria-hidden", "true");

        document.body.style.overflow = "";
        document.body.style.paddingRight = "";

        lastFocusedElement?.focus?.();
    }

    /** Resets to step 1 so a second prospect on the same page starts clean. */
    function resetDeck() {
        clearAllMarks();
        hideAlert();
        setStep(1);
        toStep2 && (toStep2.disabled = true);

        deck.querySelectorAll('input[type="radio"][name="intentTier"]').forEach((radio) => {
            radio.checked = false;
        });
        ["fullName", "companyName", "department", "email", "phone", "organizationScope", "teamSize", "preferredChannel"]
            .forEach((name) => {
                const input = fieldEl(name);
                if (input) input.value = "";
            });
        applyTrack();
        setBusy(false);
    }

    function setBusy(busy) {
        if (submitBtn) {
            submitBtn.disabled = busy;
            submitBtn.textContent = busy ? "Submitting…" : "Submit request";
        }
        toStep2 && (toStep2.disabled = busy || !selectedTier());
    }

    const selectedTier = () =>
        deck.querySelector('input[type="radio"][name="intentTier"]:checked')?.value ?? null;

    // --------------------------------------------------- asynchronous auditing

    /**
     * Audits one field server-side on blur. Failures are rendered inline and
     * never interrupt the page: no navigation, no full-form re-render.
     */
    async function auditField(name) {
        const input = fieldEl(name);
        if (!input) return true;

        const value = input.value.trim();

        // Empty values are left alone until submit, so tabbing through an
        // untouched form does not shout at the prospect.
        if (value === "") {
            markValid(name);
            fieldState[name] = undefined;
            return true;
        }

        try {
            const response = await fetch(validateUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": csrfToken,
                    Accept: "application/json",
                },
                body: JSON.stringify({ field: name, value, _token: csrfToken }),
            });

            if (!response.ok && response.status !== 422) return true;

            const data = await response.json();
            if (data.ok) {
                markValid(name);
                return true;
            }

            markInvalid(name, data.message ?? "This value was rejected.");
            return false;
        } catch {
            // A network blip must not block the prospect; the submit path
            // re-validates everything server-side anyway.
            return true;
        }
    }

    // ------------------------------------------------------------- submission

    async function submitDeck() {
        hideAlert();

        if (!selectedTier()) {
            showAlert("Please choose an engagement track before continuing.");
            setStep(1);
            return;
        }

        const payload = { intentTier: selectedTier(), _token: csrfToken };
        // The slug in intentTier identifies the service; the server reads its
        // track. Sending it explicitly too keeps the request self-describing.
        if (deck.dataset.service) payload.service = deck.dataset.service;
        auditedFields().forEach((name) => {
            const input = fieldEl(name);
            if (input && !input.disabled) payload[name] = input.value.trim();
        });
        // Team size and channel are collected for every track that asks.
        ["teamSize", "preferredChannel"].forEach((name) => {
            const input = fieldEl(name);
            if (input && !input.disabled && input.value) payload[name] = input.value.trim();
        });

        setBusy(true);

        try {
            const response = await fetch(submitUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": csrfToken,
                    Accept: "application/json",
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json().catch(() => ({}));

            if (response.ok && data.ok) {
                renderConfirmation(data);
                return;
            }

            if (data.errors) {
                Object.entries(data.errors).forEach(([field, message]) => {
                    markInvalid(field, message);
                });
                // Send the prospect back to the step that owns the first error.
                const firstBad = Object.keys(data.errors)[0];
                setStep(auditedFields().includes(firstBad) || firstBad === 'teamSize' || firstBad === 'preferredChannel' ? 2 : 1);
            }

            showAlert(data.message ?? "Something went wrong. Please try again.");
        } catch {
            showAlert("We could not reach the server. Please check your connection and try again.");
        } finally {
            setBusy(false);
        }
    }

    /** Swaps the form for the confirmation panel and wires the calendar link. */
    function renderConfirmation(data) {
        const heading = document.getElementById("funnel-confirm-heading");
        const body = document.getElementById("funnel-confirm-body");
        const ref = document.getElementById("funnel-confirm-ref");
        const tier = document.getElementById("funnel-confirm-tier");

        if (heading) heading.textContent = data.confirmation?.heading ?? "Request received";
        if (body) body.textContent = data.confirmation?.body ?? "";
        if (ref) ref.textContent = `GDC-${String(data.id).padStart(5, "0")}`;
        if (tier) tier.textContent = data.tierLabel ?? "";

        if (calendarLink) {
            // A standard Google Calendar template URL: no third-party script is
            // loaded, the link only resolves when the prospect clicks it.
            const start = new Date(Date.now() + 2 * 24 * 60 * 60 * 1000);
            const end = new Date(start.getTime() + 60 * 60 * 1000);
            const stamp = (d) => d.toISOString().replace(/[-:]|\.\d{3}/g, "");
            const params = new URLSearchParams({
                action: "TEMPLATE",
                text: `GDC Briefing · ${data.tierLabel ?? "Consultation"}`,
                dates: `${stamp(start)}/${stamp(end)}`,
                details: `Reference GDC-${String(data.id).padStart(5, "0")}`,
            });
            calendarLink.href = `https://calendar.google.com/calendar/render?${params.toString()}`;
        }

        setStep(3);
    }

    // ------------------------------------------------------------- event wiring

    deck.querySelectorAll('input[type="radio"][name="intentTier"]').forEach((radio) => {
        radio.addEventListener("change", () => {
            if (toStep2) toStep2.disabled = false;
            // Switching offer switches funnel shape: an individual enrolment must
            // not keep asking for a company name.
            applyTrack();
        });
    });

    // Blur auditing. Using focusout (which bubbles) rather than blur (which
    // does not) so a single delegated listener covers every field.
    deck.addEventListener(
        "focusout",
        (event) => {
            const name = event.target?.dataset?.funnelField;
            if (name && auditedFields().includes(name)) auditField(name);
        },
        true,
    );

    toStep2?.addEventListener("click", () => {
        hideAlert();
        if (selectedTier()) setStep(2);
    });

    backBtn?.addEventListener("click", () => {
        hideAlert();
        setStep(1);
    });

    submitBtn?.addEventListener("click", submitDeck);

    closeBtn?.addEventListener("click", closeDeck);
    doneBtn?.addEventListener("click", () => {
        closeDeck();
        resetDeck();
    });

    overlay?.addEventListener("click", closeDeck);

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && isOpen) closeDeck();
    });

    // ------------------------------------------------------------- initial state

    // On a service page the deck is already scoped to that offer, so pre-select
    // it. The prospect then lands on the right funnel shape without choosing
    // twice. On pages with no service, nothing is selected and step 1 asks.
    if (deck.dataset.service) {
        const preset = deck.querySelector(`input[type="radio"][name="intentTier"][value="${CSS.escape(deck.dataset.service)}"]`);
        if (preset) {
            preset.checked = true;
            if (toStep2) toStep2.disabled = false;
        }
    }

    // Hide the field groups the selected track does not ask for before first
    // paint of the deck, so an individual enrolee never sees a company field.
    applyTrack();

    // Any element on the page may open the deck via [data-funnel-open].
    document.querySelectorAll("[data-funnel-open]").forEach((trigger) => {
        trigger.addEventListener("click", (event) => {
            event.preventDefault();
            openDeck();
        });
    });

    // The header CTA points at #inquiry on the training page but should open
    // the deck from anywhere.
    document.querySelectorAll('a[href="#inquiry"]').forEach((link) => {
        if (link.hasAttribute("data-funnel-open")) return;
        link.setAttribute("data-funnel-open", "");
        link.addEventListener("click", (event) => {
            event.preventDefault();
            openDeck();
        });
    });
})();
