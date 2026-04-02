<!DOCTYPE html>
<html lang="<?= htmlspecialchars($tenantConfig['locale'] ?? 'en') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="description" content="Book an appointment with <?= htmlspecialchars($tenant['name']) ?>">
    <title>Book – <?= htmlspecialchars($tenant['name']) ?></title>

    <!-- Brand tokens (per-tenant) -->
    <style><?= $brandStyle ?></style>

    <!-- Inter Variable (self-hosted) -->
    <link rel="preconnect" href="/assets/fonts/">
    <link rel="stylesheet" href="/assets/css/booking-css.css">

    <!-- Anti-FOUC: hide until Alpine is ready -->
    <style>
        [x-cloak] { display: none !important; }
    </style>

    <!-- Theme bootstrap: runs before paint to prevent flash of wrong theme -->
    <script>
        (function() {
            var s = localStorage.getItem('vb-theme');
            var t = s || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
</head>
<body>
    <div class="vb-book-app" x-data="bookingWizard" x-cloak
         x-init="$el.removeAttribute('x-cloak')"
         id="vb-book-app">

        <!-- ── Header ── -->
        <header class="vb-book-header">
            <div class="vb-book-header-inner">
                <?php if (!empty($tenant['logo_path'])): ?>
                    <img
                        src="/uploads/<?= htmlspecialchars($tenant['slug']) ?>/<?= htmlspecialchars($tenant['logo_path']) ?>"
                        alt="<?= htmlspecialchars($tenant['name']) ?>"
                        class="vb-book-logo"
                    >
                <?php endif; ?>
                <h1 class="vb-book-business-name"><?= htmlspecialchars($tenant['booking_page_heading'] ?: $tenant['name']) ?></h1>
                <?php if (!empty($tenant['booking_page_description'])): ?>
                    <p class="vb-book-business-desc"><?= htmlspecialchars($tenant['booking_page_description']) ?></p>
                <?php endif; ?>
            </div>

            <!-- ── Progress Dots ── -->
            <div class="vb-book-progress-wrap" x-show="showProgress">
                <div class="vb-book-progress">
                    <template x-for="(s, i) in progressSteps" x-bind:key="s">
                        <div class="vb-book-progress-dot"
                             x-bind:class="{
                                 'is-active': isProgressDotActive(i),
                                 'is-completed': isProgressDotCompleted(i)
                             }"></div>
                    </template>
                </div>
            </div>
        </header>

        <!-- ── Timezone Selector ── -->
        <div class="vb-book-tz-bar" x-show="showProgress">
            <div class="vb-book-tz-inner">
                <button class="vb-book-tz-trigger" type="button" x-bind:aria-expanded="tzDropdownOpen" @click="toggleTzDropdown">
                    <i data-lucide="globe" class="vb-book-tz-icon"></i>
                    <span class="vb-book-tz-label" x-text="tzDisplayLabel(customerTz)"></span>
                    <span class="vb-book-tz-badge" x-show="tzMatch" x-text="t('timezone.same_as_business')"></span>
                    <i data-lucide="chevron-down" class="vb-book-tz-chevron"></i>
                </button>

                <div class="vb-book-tz-dropdown" x-show="tzDropdownOpen" @click.outside="closeTzDropdown" @keydown.escape.window="closeTzDropdown">
                    <div class="vb-book-tz-search-wrap">
                        <input type="text" class="vb-book-tz-search" x-ref="tzSearch"
                               x-bind:value="tzSearchQuery"
                               @input="setTzSearchQuery($el.value)"
                               placeholder="<?= __('booking.timezone.search') ?>"
                               autocomplete="off">
                    </div>
                    <div class="vb-book-tz-list">
                        <template x-for="group in tzGroups" x-bind:key="group.label">
                            <div class="vb-book-tz-group">
                                <div class="vb-book-tz-group-label" x-text="group.label"></div>
                                <template x-for="zone in group.zones" x-bind:key="zone">
                                    <button type="button" class="vb-book-tz-option"
                                            x-bind:class="{ 'is-selected': isZoneSelected(zone) }"
                                            @click="selectTimezone(zone)"
                                            x-text="tzDisplayLabel(zone)">
                                    </button>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Main Flow ── -->
        <main class="vb-book-flow" id="vb-book-flow">

            <!-- Loading shimmer skeleton -->
            <div x-show="isLoading" x-cloak class="vb-book-step">
                <div class="vb-book-shimmer-block">
                    <div class="vb-book-shimmer-title"></div>
                    <div class="vb-book-shimmer-card"></div>
                    <div class="vb-book-shimmer-card"></div>
                    <div class="vb-book-shimmer-card vb-book-shimmer-card-short"></div>
                </div>
            </div>

            <!-- Empty state -->
            <div x-show="isEmpty" x-cloak class="vb-book-step">
                <div class="vb-book-step-header">
                    <div class="vb-book-step-title" x-text="t('empty.no_services')"></div>
                    <div class="vb-book-step-subtitle" x-text="t('empty.no_services_desc')"></div>
                </div>
            </div>

            <!-- Unsupported pattern -->
            <div x-show="isUnsupported" x-cloak class="vb-book-step">
                <div class="vb-book-step-header">
                    <div class="vb-book-step-title" x-text="t('empty.coming_soon')"></div>
                    <div class="vb-book-step-subtitle" x-text="t('empty.coming_soon_desc')"></div>
                </div>
            </div>

            <!-- ═══ Resource Step 1: Room Selection ═══ -->
            <div x-show="isResourceStep" x-cloak class="vb-book-step" x-transition>
                <div class="vb-book-step-header">
                    <div class="vb-book-step-title" x-text="t('steps.resource_title')"></div>
                </div>
                <div class="vb-book-service-list" role="radiogroup">
                    <template x-for="(resource, ri) in resources" x-bind:key="resource.id">
                        <div class="vb-book-service-card"
                             x-bind:class="{ 'is-selected': isResourceSelected(resource) }"
                             x-bind:style="serviceAnimDelay(ri)"
                             @click="selectResource(resource)"
                             role="radio" tabindex="0"
                             x-bind:aria-checked="isResourceSelected(resource)"
                             @keydown.enter="selectResource(resource)"
                             @keydown.space.prevent="selectResource(resource)">
                            <div class="vb-book-service-info">
                                <div class="vb-book-service-name" x-text="resource.name"></div>
                                <div class="vb-book-service-meta">
                                    <span x-text="t('resource.capacity_label', { count: resource.capacity })"></span>
                                    <template x-if="resource.min_stay_nights || resource.max_stay_nights">
                                        <span>· <span x-text="t('resource.stay_range', { min: resource.min_stay_nights, max: resource.max_stay_nights })"></span></span>
                                    </template>
                                </div>
                                <template x-if="resource.description">
                                    <div class="vb-book-service-desc" x-text="resource.description"></div>
                                </template>
                            </div>
                            <template x-if="resource.price_per_night">
                                <div class="vb-book-service-price" x-text="formatPrice(resource.price_per_night) + t('resource.per_night')"></div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            <!-- ═══ Resource Step 2: Date Range ═══ -->
            <div x-show="isResourceDateStep" x-cloak class="vb-book-step" x-transition>
                <div class="vb-book-step-header">
                    <div class="vb-book-step-title" x-text="t('steps.dates_title')"></div>
                    <div class="vb-book-step-subtitle" x-text="checkInDate ? t('resource.select_check_out') : t('resource.select_check_in')"></div>
                </div>

                <!-- Calendar (reused structure) -->
                <div class="vb-book-calendar" role="grid">
                    <div class="vb-book-calendar-nav">
                        <button class="vb-book-calendar-btn" @click="prevResourceMonth" x-bind:disabled="!canPrevResourceMonth" aria-label="Previous month">
                            <i data-lucide="chevron-left"></i>
                        </button>
                        <span class="vb-book-calendar-month" x-text="resourceMonthLabel"></span>
                        <button class="vb-book-calendar-btn" @click="nextResourceMonth" aria-label="Next month">
                            <i data-lucide="chevron-right"></i>
                        </button>
                    </div>
                    <div class="vb-book-calendar-grid">
                        <!-- Day name headers -->
                        <template x-for="d in dayNames" x-bind:key="d">
                            <div class="vb-book-calendar-dayname" x-text="d"></div>
                        </template>
                        <!-- Calendar cells -->
                        <template x-for="cell in resourceCalendarCells" x-bind:key="cellKey(cell)">
                            <div class="vb-book-calendar-cell"
                                 x-bind:class="{
                                     'is-disabled': cell.disabled,
                                     'is-today': cell.today,
                                     'has-slots': cell.hasSlots,
                                     'is-selected': cell.selected,
                                     'is-range': cell.inRange
                                 }"
                                 x-bind:tabindex="cellTabindex(cell)"
                                 x-bind:role="cellRole(cell)"
                                 x-bind:aria-disabled="cell.disabled"
                                 x-bind:aria-selected="cell.selected"
                                 @click="clickResourceDate(cell)"
                                 @keydown.enter="clickResourceDate(cell)"
                                 @keydown.space.prevent="clickResourceDate(cell)"
                                 x-text="cell.day">
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Back link -->
                <template x-if="resourceDateBackTarget">
                    <div class="vb-book-back-link">
                        <button type="button" class="vb-book-btn vb-book-btn-ghost" @click="goBack(resourceDateBackTarget)" x-text="t('back.change_service')"></button>
                    </div>
                </template>
            </div>

            <!-- ═══ Resource Step 3: Guest Count ═══ -->
            <div x-show="isGuestStep" x-cloak class="vb-book-step" x-transition>
                <div class="vb-book-step-header">
                    <div class="vb-book-step-title" x-text="t('steps.guests_title')"></div>
                </div>

                <div class="vb-book-summary" style="margin-bottom: 1.5rem;">
                    <div class="vb-book-summary-row">
                        <span class="vb-book-summary-label" x-text="t('resource.summary_resource')"></span>
                        <span class="vb-book-summary-value" x-text="selectedResource ? selectedResource.name : ''"></span>
                    </div>
                    <div class="vb-book-summary-row">
                        <span class="vb-book-summary-label" x-text="t('resource.check_in_label')"></span>
                        <span class="vb-book-summary-value" x-text="formatDateDisplay(checkInDate)"></span>
                    </div>
                    <div class="vb-book-summary-row">
                        <span class="vb-book-summary-label" x-text="t('resource.check_out_label')"></span>
                        <span class="vb-book-summary-value" x-text="formatDateDisplay(checkOutDate)"></span>
                    </div>
                    <template x-if="resourceAvailability">
                        <div class="vb-book-summary-row">
                            <span class="vb-book-summary-label" x-text="t('resource.total_label')"></span>
                            <span class="vb-book-summary-value" x-text="formatPrice(resourceAvailability.total)"></span>
                        </div>
                    </template>
                </div>

                <div class="vb-book-party-size">
                    <div class="vb-book-counter">
                        <button type="button" class="vb-book-counter-btn" @click="decrementGuests"
                                x-bind:disabled="guestCount <= 1">
                            <i data-lucide="minus"></i>
                        </button>
                        <span class="vb-book-counter-value" x-text="guestCount"></span>
                        <button type="button" class="vb-book-counter-btn" @click="incrementGuests"
                                x-bind:disabled="guestCount >= guestMax">
                            <i data-lucide="plus"></i>
                        </button>
                    </div>
                    <div class="vb-book-counter-label" x-text="guestCount === 1 ? t('capacity.guest') : t('capacity.guests')"></div>
                </div>

                <div class="vb-book-form-actions" style="margin-top: 1rem;">
                    <button type="button" class="vb-book-btn vb-book-btn-primary" @click="submitGuests"
                            x-text="t('buttons.continue')"></button>
                    <div class="vb-book-back-link">
                        <button type="button" class="vb-book-btn vb-book-btn-ghost" @click="goBack('resource-date')" x-text="t('back.change_date')"></button>
                    </div>
                </div>
            </div>

            <!-- ═══ Capacity: Step 1 — Party Size ═══ -->
            <div x-show="isPartySizeStep" x-cloak class="vb-book-step" x-transition>
                <div class="vb-book-step-header">
                    <div class="vb-book-step-title" x-text="t('capacity.party_size_title')"></div>
                    <div class="vb-book-step-subtitle" x-text="t('capacity.party_size_hint')"></div>
                </div>
                <div class="vb-book-party-size">
                    <div class="vb-book-counter">
                        <button type="button" class="vb-book-counter-btn" @click="decrementPartySize"
                                x-bind:disabled="partySize <= minPartySize">
                            <i data-lucide="minus"></i>
                        </button>
                        <span class="vb-book-counter-value" x-text="partySize"></span>
                        <button type="button" class="vb-book-counter-btn" @click="incrementPartySize"
                                x-bind:disabled="partySize >= maxPartySize">
                            <i data-lucide="plus"></i>
                        </button>
                    </div>
                    <div class="vb-book-counter-label" x-text="partySize === 1 ? t('capacity.guest') : t('capacity.guests')"></div>
                    <template x-if="minPartySize > 1">
                        <div class="vb-book-counter-hint" x-text="t('capacity.min_guests_hint').replace(':count', minPartySize)" style="margin-top: 0.5rem; font-size: 0.8rem; opacity: 0.6;"></div>
                    </template>
                </div>
                <div class="vb-book-form-actions" style="margin-top: 1.5rem;">
                    <button type="button" class="vb-book-btn vb-book-btn-primary" @click="confirmPartySize"
                            x-text="t('buttons.continue')"></button>
                </div>
            </div>

            <!-- ═══ Capacity: Step 2 — Date Selection ═══ -->
            <div x-show="isCapacityDateStep" x-cloak class="vb-book-step" x-transition>
                <div class="vb-book-step-header">
                    <div class="vb-book-step-title" x-text="t('capacity.date_title')"></div>
                </div>
                <div class="vb-book-calendar" role="grid">
                    <div class="vb-book-calendar-nav">
                        <button type="button" class="vb-book-calendar-btn" @click="prevCapacityMonth" aria-label="<?= __('booking.calendar.prev_month') ?>">
                            <i data-lucide="chevron-left"></i>
                        </button>
                        <span class="vb-book-calendar-month" x-text="capacityMonthLabel"></span>
                        <button type="button" class="vb-book-calendar-btn" @click="nextCapacityMonth" aria-label="<?= __('booking.calendar.next_month') ?>">
                            <i data-lucide="chevron-right"></i>
                        </button>
                    </div>
                    <div class="vb-book-calendar-grid">
                        <!-- Day name headers -->
                        <template x-for="d in dayNames" x-bind:key="d">
                            <div class="vb-book-calendar-dayname" x-text="d"></div>
                        </template>
                        <!-- Calendar cells -->
                        <template x-for="(cell, ci) in capacityCalendarGrid" x-bind:key="ci">
                            <div class="vb-book-calendar-cell"
                                 x-bind:class="{
                                     'is-disabled': cell.disabled,
                                     'is-today': cell.isToday,
                                     'has-slots': !cell.disabled && cell.day,
                                     'is-selected': cell.isSelected
                                 }"
                                 x-bind:tabindex="cell.day && !cell.disabled ? 0 : -1"
                                 x-bind:role="cell.day ? 'gridcell' : 'presentation'"
                                 x-bind:aria-disabled="cell.disabled"
                                 x-bind:aria-selected="cell.isSelected"
                                 @click="selectCapacityDate(cell)"
                                 @keydown.enter="selectCapacityDate(cell)"
                                 @keydown.space.prevent="selectCapacityDate(cell)"
                                 x-text="cell.day || ''">
                            </div>
                        </template>
                    </div>
                </div>
                <div class="vb-book-form-actions" style="margin-top: 1rem;">
                    <div class="vb-book-back-link">
                        <button type="button" class="vb-book-btn vb-book-btn-ghost" @click="goBack('party-size')"
                                x-text="t('back.change_party_size')"></button>
                    </div>
                </div>
            </div>

            <!-- ═══ Capacity: Step 3 — Time Slot Selection ═══ -->
            <div x-show="isCapacityTimeStep" x-cloak class="vb-book-step" x-transition>
                <div class="vb-book-step-header">
                    <div class="vb-book-step-title" x-text="t('capacity.time_title')"></div>
                    <div class="vb-book-step-subtitle" x-text="formatDateDisplay(selectedDate)"></div>
                </div>
                <div class="vb-book-slot-list" role="radiogroup">
                    <template x-for="slot in capacitySlots" x-bind:key="slot.id">
                        <button type="button"
                                class="vb-book-slot-card"
                                role="radio"
                                x-bind:aria-checked="selectedCapacitySlot && selectedCapacitySlot.id === slot.id"
                                @click="selectCapacitySlot(slot)">
                            <div class="vb-book-slot-time">
                                <span x-text="formatCapacitySlotTime(slot.time) + ' – ' + formatCapacitySlotTime(slot.end_time)"></span>
                                <span class="vb-book-slot-label" x-show="slot.label" x-text="slot.label"></span>
                            </div>
                            <div class="vb-book-slot-meta">
                                <span class="vb-book-slot-remaining" x-text="t('capacity.spots_remaining').replace(':count', slot.remaining)"></span>
                            </div>
                        </button>
                    </template>
                </div>
                <div x-show="capacitySlots.length === 0" class="vb-book-empty">
                    <span x-text="t('empty.no_slots_date')"></span>
                </div>
                <div class="vb-book-form-actions" style="margin-top: 1rem;">
                    <div class="vb-book-back-link">
                        <button type="button" class="vb-book-btn vb-book-btn-ghost" @click="goBack('capacity-date')"
                                x-text="t('back.change_date_cap')"></button>
                    </div>
                </div>
            </div>

            <!-- ═══ Event Step 1: Event List ═══ -->
            <div x-show="isEventListStep" x-cloak class="vb-book-step" x-transition>
                <div class="vb-book-step-header">
                    <div class="vb-book-step-title" x-text="t('event.events_title')"></div>
                </div>
                <div class="vb-book-event-list">
                    <template x-for="(event, i) in eventList" :key="event.id + '-' + (event.date || '')">
                        <button type="button"
                                class="vb-book-event-card"
                                @click="selectEvent(event)"
                                :class="{ 'is-full': event.remaining <= 0 && !event.allow_waitlist }">
                            <div class="vb-book-event-card-header">
                                <span class="vb-book-event-name" x-text="event.name"></span>
                                <span class="vb-book-event-price" x-text="formatEventPrice(event.price)"></span>
                            </div>
                            <div class="vb-book-event-card-meta">
                                <span class="vb-book-event-date">
                                    <i data-lucide="calendar" style="width:14px;height:14px;"></i>
                                    <span x-text="formatEventDate(event.start_datetime)"></span>
                                </span>
                                <span class="vb-book-event-time">
                                    <i data-lucide="clock" style="width:14px;height:14px;"></i>
                                    <span x-text="formatEventTime(event.start_datetime) + ' – ' + formatEventTime(event.end_datetime)"></span>
                                </span>
                                <span x-show="event.location" class="vb-book-event-location">
                                    <i data-lucide="map-pin" style="width:14px;height:14px;"></i>
                                    <span x-text="event.location"></span>
                                </span>
                            </div>
                            <div class="vb-book-event-card-footer">
                                <span x-show="event.remaining > 0"
                                      class="vb-book-event-spots"
                                      x-text="t('event.spots_remaining').replace(':count', event.remaining)"></span>
                                <span x-show="event.remaining <= 0 && event.allow_waitlist"
                                      class="vb-book-event-badge vb-book-event-badge-waitlist"
                                      x-text="t('event.waitlist_badge')"></span>
                                <span x-show="event.remaining <= 0 && !event.allow_waitlist"
                                      class="vb-book-event-badge vb-book-event-badge-full"
                                      x-text="t('event.full_badge')"></span>
                            </div>
                        </button>
                    </template>
                </div>
                <div x-show="eventList.length === 0" class="vb-book-empty">
                    <span x-text="t('empty.no_events')"></span>
                </div>
            </div>

            <!-- ═══ Event Step 2: Event Detail ═══ -->
            <div x-show="isEventDetailStep" x-cloak class="vb-book-step" x-transition>
                <div class="vb-book-step-header">
                    <div class="vb-book-step-title" x-text="t('event.event_detail_title')"></div>
                </div>
                <div class="vb-book-event-detail" x-show="selectedEvent">
                    <h3 class="vb-book-event-detail-name" x-text="selectedEvent ? selectedEvent.name : ''"></h3>
                    <p class="vb-book-event-detail-desc" x-show="selectedEvent && selectedEvent.description" x-text="selectedEvent ? selectedEvent.description : ''"></p>

                    <div class="vb-book-event-detail-grid">
                        <div class="vb-book-event-detail-row">
                            <span class="vb-book-event-detail-label" x-text="t('event.date_label')"></span>
                            <span x-text="formatEventDate(selectedEvent ? selectedEvent.start_datetime : '')"></span>
                        </div>
                        <div class="vb-book-event-detail-row">
                            <span class="vb-book-event-detail-label" x-text="t('event.time_label')"></span>
                            <span x-text="(selectedEvent ? formatEventTime(selectedEvent.start_datetime) + ' – ' + formatEventTime(selectedEvent.end_datetime) : '')"></span>
                        </div>
                        <div class="vb-book-event-detail-row" x-show="selectedEvent && selectedEvent.location">
                            <span class="vb-book-event-detail-label" x-text="t('event.location_label')"></span>
                            <span x-text="selectedEvent ? selectedEvent.location : ''"></span>
                        </div>
                        <div class="vb-book-event-detail-row">
                            <span class="vb-book-event-detail-label" x-text="t('event.price_label')"></span>
                            <span x-text="formatEventPrice(selectedEvent ? selectedEvent.price : 0)"></span>
                        </div>
                        <div class="vb-book-event-detail-row">
                            <span class="vb-book-event-detail-label" x-text="t('event.spots_remaining').replace(':count', '')"></span>
                            <span x-text="(selectedEvent ? selectedEvent.remaining + ' / ' + selectedEvent.max_participants : '')"></span>
                        </div>
                    </div>

                    <div class="vb-book-form-actions" style="margin-top: 1.5rem;">
                        <button type="button" class="vb-book-btn vb-book-btn-primary" @click="confirmEventDetail"
                                x-text="t('buttons.continue')"></button>
                        <div class="vb-book-back-link">
                            <button type="button" class="vb-book-btn vb-book-btn-ghost" @click="goBack('event-list')"
                                    x-text="t('back.change_event')"></button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══ Event Step 3: Spot Count ═══ -->
            <div x-show="isEventSpotsStep" x-cloak class="vb-book-step" x-transition>
                <div class="vb-book-step-header">
                    <div class="vb-book-step-title" x-text="t('event.spots_title')"></div>
                </div>
                <div class="vb-book-party-size">
                    <div class="vb-book-counter">
                        <button type="button" class="vb-book-counter-btn" @click="decrementEventSpots"
                                x-bind:disabled="eventSpotCount <= eventMinSpots">
                            <i data-lucide="minus"></i>
                        </button>
                        <span class="vb-book-counter-value" x-text="eventSpotCount"></span>
                        <button type="button" class="vb-book-counter-btn" @click="incrementEventSpots"
                                x-bind:disabled="eventSpotCount >= eventMaxSpots">
                            <i data-lucide="plus"></i>
                        </button>
                    </div>
                    <div class="vb-book-counter-label" x-text="eventSpotCount === 1 ? t('event.spot') : t('event.spots')"></div>
                    <template x-if="eventSpotCount >= eventMaxSpots && selectedEvent && selectedEvent.remaining > 0">
                        <div class="vb-book-counter-hint" style="margin-top: 0.5rem; font-size: 0.8rem; opacity: 0.6;"
                             x-text="selectedEvent.max_spot_count && eventSpotCount >= selectedEvent.max_spot_count
                                 ? t('event.max_spots_reached').replace(':count', selectedEvent.max_spot_count)
                                 : t('event.max_reached')">
                        </div>
                    </template>
                </div>
                <div class="vb-book-form-actions" style="margin-top: 1.5rem;">
                    <button type="button" class="vb-book-btn vb-book-btn-primary" @click="confirmEventSpots"
                            x-text="t('buttons.continue')"></button>
                    <div class="vb-book-back-link">
                        <button type="button" class="vb-book-btn vb-book-btn-ghost" @click="goBack('event-detail')"
                                x-text="t('back.change_event')"></button>
                    </div>
                </div>
            </div>

            <!-- ═══ Step 1: Service Selection ═══ -->
            <div x-show="isServiceStep" x-cloak class="vb-book-step" x-transition>
                <div class="vb-book-step-header">
                    <div class="vb-book-step-title" x-text="t('steps.service_title')"></div>
                </div>
                <div class="vb-book-service-list" role="radiogroup">
                    <template x-for="(service, si) in services" x-bind:key="service.id">
                        <div class="vb-book-service-card"
                             x-bind:class="{ 'is-selected': isServiceSelected(service) }"
                             x-bind:style="serviceAnimDelay(si)"
                             @click="selectService(service)"
                             role="radio" tabindex="0"
                             x-bind:aria-checked="isServiceSelected(service)"
                             @keydown.enter="selectService(service)"
                             @keydown.space.prevent="selectService(service)">
                            <div class="vb-book-service-info">
                                <div class="vb-book-service-name" x-text="service.name"></div>
                                <div class="vb-book-service-meta">
                                    <span x-text="formatDuration(service.duration_minutes)"></span>
                                </div>
                                <template x-if="service.description">
                                    <div class="vb-book-service-desc" x-text="service.description"></div>
                                </template>
                            </div>
                            <template x-if="hasPrice(service)">
                                <div class="vb-book-service-price" x-text="servicePriceLabel(service)"></div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            <!-- ═══ Step 2: Staff Selection ═══ -->
            <div x-show="isStaffStep" x-cloak class="vb-book-step" x-transition>
                <div class="vb-book-step-header">
                    <div class="vb-book-step-title" x-text="t('steps.staff_title')"></div>
                    <div class="vb-book-step-subtitle" x-text="t('steps.staff_subtitle')"></div>
                </div>
                <div class="vb-book-staff-grid" role="radiogroup">
                    <!-- Any available -->
                    <div class="vb-book-staff-card"
                         x-bind:class="{ 'is-selected': isAnyStaffSelected() }"
                         @click="selectAnyStaff"
                         role="radio" tabindex="0"
                         x-bind:aria-checked="isAnyStaffSelected()">
                        <div class="vb-book-staff-avatar">
                            <i data-lucide="users"></i>
                        </div>
                        <div class="vb-book-staff-name" x-text="t('staff.any_available')"></div>
                    </div>
                    <!-- Staff members -->
                    <template x-for="member in staff" x-bind:key="member.id">
                        <div class="vb-book-staff-card"
                             x-bind:class="{ 'is-selected': isStaffSelected(member) }"
                             @click="selectStaff(member)"
                             role="radio" tabindex="0"
                             x-bind:aria-checked="isStaffSelected(member)">
                            <div class="vb-book-staff-avatar">
                                <template x-if="hasAvatar(member)">
                                    <img x-bind:src="avatarUrl(member)" x-bind:alt="member.name">
                                </template>
                                <template x-if="noAvatar(member)">
                                    <span x-text="staffInitials(member.name)"></span>
                                </template>
                            </div>
                            <div class="vb-book-staff-name" x-text="member.name"></div>
                            <template x-if="member.title">
                                <div class="vb-book-staff-title" x-text="member.title"></div>
                            </template>
                        </div>
                    </template>
                </div>
                <!-- Back link -->
                <template x-if="showStaffBackLink">
                    <div class="vb-book-back-link">
                        <button type="button" class="vb-book-btn vb-book-btn-ghost" @click="goBack('service')" x-text="t('back.change_service')"></button>
                    </div>
                </template>
            </div>

            <!-- ═══ Step 3: Date & Time ═══ -->
            <div x-show="isDateStep" x-cloak class="vb-book-step" x-transition>
                <div class="vb-book-step-header">
                    <div class="vb-book-step-title" x-text="t('steps.date_title')"></div>
                </div>

                <!-- Timezone mismatch notice -->
                <div class="vb-book-tz-notice" x-show="isTzMismatch">
                    <i data-lucide="globe" class="vb-book-tz-notice-icon"></i>
                    <span x-text="t('timezone.notice', { tz: tzDisplayLabel(customerTz) })"></span>
                </div>

                <!-- Calendar -->
                <div class="vb-book-calendar" role="grid">
                    <div class="vb-book-calendar-nav">
                        <button class="vb-book-calendar-btn" @click="prevMonth" x-bind:disabled="!canPrevMonth" aria-label="Previous month">
                            <i data-lucide="chevron-left"></i>
                        </button>
                        <span class="vb-book-calendar-month" x-text="monthLabel"></span>
                        <button class="vb-book-calendar-btn" @click="nextMonth" aria-label="Next month">
                            <i data-lucide="chevron-right"></i>
                        </button>
                    </div>
                    <div class="vb-book-calendar-grid"
                         x-bind:class="{ 'is-fading': isCalendarFading }">
                        <!-- Day name headers -->
                        <template x-for="d in dayNames" x-bind:key="d">
                            <div class="vb-book-calendar-dayname" x-text="d"></div>
                        </template>
                        <!-- Calendar cells -->
                        <template x-for="cell in calendarCells" x-bind:key="cellKey(cell)">
                            <div class="vb-book-calendar-cell"
                                 x-bind:class="{
                                     'is-disabled': cell.disabled,
                                     'is-today': cell.today,
                                     'has-slots': cell.hasSlots,
                                     'is-selected': cell.selected
                                 }"
                                 x-bind:tabindex="cellTabindex(cell)"
                                 x-bind:role="cellRole(cell)"
                                 x-bind:aria-disabled="cell.disabled"
                                 x-bind:aria-selected="cell.selected"
                                 @click="clickDate(cell)"
                                 @keydown.enter="clickDate(cell)"
                                 @keydown.space.prevent="clickDate(cell)"
                                 x-text="cell.day">
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Time Slots -->
                <div id="vb-time-container" x-show="hasSelectedDate">
                    <template x-if="hasNoSlots">
                        <div class="vb-book-empty" x-text="t('empty.no_times')"></div>
                    </template>
                    <template x-if="hasSlots">
                        <div class="vb-book-time-grid" role="radiogroup">
                            <template x-for="(slot, i) in availableSlots" x-bind:key="slot.time">
                                <div class="vb-book-time-pill"
                                     x-bind:class="{
                                         'is-selected': isSlotSelected(slot),
                                         'is-dimmed': isSlotDimmed(slot)
                                     }"
                                     @click="selectSlot(slot)"
                                     @keydown.enter="selectSlot(slot)"
                                     @keydown.space.prevent="selectSlot(slot)"
                                     role="radio" tabindex="0"
                                     x-bind:aria-checked="isSlotSelected(slot)"
                                     x-bind:style="slotAnimDelay(i)"
                                     x-text="displaySlotTime(slot)">
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <!-- Back link -->
                <template x-if="dateBackTarget">
                    <div class="vb-book-back-link">
                        <button type="button" class="vb-book-btn vb-book-btn-ghost" @click="goBack(dateBackTarget)"
                                x-text="dateBackLabel"></button>
                    </div>
                </template>
            </div>

            <!-- ═══ Step 4: Customer Details ═══ -->
            <div x-show="isDetailsStep" x-cloak class="vb-book-step" x-transition>
                <div class="vb-book-step-header">
                    <div class="vb-book-step-title" x-text="t('steps.details_title')"></div>
                    <div class="vb-book-step-subtitle" x-text="t('steps.details_subtitle')"></div>
                </div>

                <form @submit.prevent="submitDetails" novalidate>
                    <div class="vb-book-form-group">
                        <label class="vb-book-label" for="vb-name"><?= __('booking.form.name_label') ?> <span class="vb-book-required" aria-hidden="true">*</span></label>
                        <input class="vb-book-input" id="vb-name" type="text" required
                               x-bind:value="customerName"
                               @input="setCustomerName($el.value)"
                               x-bind:class="{ 'has-error': hasError('name') }"
                               placeholder="<?= __('booking.form.name_placeholder') ?>"
                               autocomplete="name">
                    </div>

                    <div class="vb-book-form-group">
                        <label class="vb-book-label" for="vb-email"><?= __('booking.form.email_label') ?> <span class="vb-book-required" aria-hidden="true">*</span></label>
                        <input class="vb-book-input" id="vb-email" type="email" required
                               x-bind:value="customerEmail"
                               @input="setCustomerEmail($el.value)"
                               x-bind:class="{ 'has-error': hasError('email') }"
                               placeholder="<?= __('booking.form.email_placeholder') ?>"
                               autocomplete="email">
                    </div>

                    <?php /* Phone field — only rendered when require_phone is true */ ?>
                    <template x-if="config.require_phone">
                        <div class="vb-book-form-group">
                            <label class="vb-book-label" for="vb-phone"><?= __('booking.form.phone_label') ?> <span class="vb-book-required" aria-hidden="true">*</span></label>
                            <input class="vb-book-input" id="vb-phone" type="tel" required
                                   x-bind:value="customerPhone"
                                   @input="setCustomerPhone($el.value)"
                                   x-bind:class="{ 'has-error': hasError('phone') }"
                                   placeholder="+31 6 12345678"
                                   autocomplete="tel">
                        </div>
                    </template>

                    <div class="vb-book-form-group">
                        <label class="vb-book-label" for="vb-notes"><?= __('booking.form.notes_label') ?></label>
                        <textarea class="vb-book-textarea" id="vb-notes"
                                  x-bind:value="customerNotes"
                                  @input="setCustomerNotes($el.value)"
                                  placeholder="<?= __('booking.form.notes_placeholder') ?>"></textarea>
                    </div>

                    <!-- Custom Fields -->
                    <template x-for="field in config.custom_fields" x-bind:key="field.name">
                        <div class="vb-book-form-group">
                            <label class="vb-book-label" x-bind:for="customFieldId(field)"
                                   x-text="fieldLabel(field)"></label>
                            <template x-if="isTextarea(field)">
                                <textarea class="vb-book-textarea" x-bind:id="customFieldId(field)"
                                          x-bind:data-book-custom="field.name"
                                          x-bind:placeholder="fieldPlaceholder(field)"
                                          x-bind:required="field.required"></textarea>
                            </template>
                            <template x-if="isNotTextarea(field)">
                                <input class="vb-book-input" x-bind:id="customFieldId(field)" type="text"
                                       x-bind:data-book-custom="field.name"
                                       x-bind:placeholder="fieldPlaceholder(field)"
                                       x-bind:required="field.required">
                            </template>
                        </div>
                    </template>

                    <!-- Consent -->
                    <template x-if="config.requires_consent">
                        <div class="vb-book-consent" x-bind:class="{ 'has-error': hasError('consent') }">
                            <input type="checkbox" class="vb-book-consent-checkbox" id="vb-consent"
                                   x-bind:checked="consentGiven"
                                   @change="setConsentGiven($el.checked)"
                                   aria-required="true">
                            <label class="vb-book-consent-label" for="vb-consent" x-text="consentLabel()"></label>
                        </div>
                    </template>

                    <!-- Honeypot: invisible to humans, caught by bots -->
                    <div class="vb-book-hp" aria-hidden="true" tabindex="-1">
                        <input type="text" name="__hp" autocomplete="off" tabindex="-1">
                    </div>

                    <div class="vb-book-form-actions">
                        <button type="submit" class="vb-book-btn vb-book-btn-primary">
                            <span class="vb-book-btn-text" x-text="t('buttons.review')"></span>
                        </button>
                        <div class="vb-book-back-link">
                            <button type="button" class="vb-book-btn vb-book-btn-ghost" @click="goBack(activeDetailsBackTarget)" x-text="t('back.change_date')"></button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- ═══ Step 5: Review / Summary ═══ -->
            <div x-show="isReviewStep" x-cloak class="vb-book-step" x-transition>
                <div class="vb-book-step-header">
                    <div class="vb-book-step-title" x-text="t('steps.confirm_title')"></div>
                    <div class="vb-book-step-subtitle" x-text="t('steps.confirm_subtitle')"></div>
                </div>

                <div class="vb-book-summary">
                    <template x-for="row in activeReviewRows" x-bind:key="row.label">
                        <div class="vb-book-summary-row">
                            <span class="vb-book-summary-label" x-text="row.label"></span>
                            <span class="vb-book-summary-value" x-text="row.value"></span>
                        </div>
                    </template>
                </div>

                <!-- Preparation callout (service-level, e.g. "Please arrive 10 minutes early") -->
                <template x-if="preparationText">
                    <div class="vb-book-preparation-callout" x-text="preparationText"></div>
                </template>

                <!-- Slot-taken recovery panel -->
                <template x-if="slotAlternatives.length > 0">
                    <div class="vb-book-slot-recovery">
                        <p class="vb-book-slot-recovery-msg" x-text="t('recovery.slot_taken')"></p>
                        <div class="vb-book-slot-recovery-pills">
                            <template x-for="alt in slotAlternatives" x-bind:key="alt.time">
                                <button type="button" class="vb-book-slot-pill"
                                        @click="selectAlternative(alt)"
                                        x-text="formatSlotTime(alt.time)"></button>
                            </template>
                        </div>
                    </div>
                </template>

                <div class="vb-book-form-actions">
                    <button class="vb-book-btn vb-book-btn-primary" @click="activeSubmitHandler()"
                            x-bind:disabled="submitting"
                            x-bind:class="{ 'is-loading': submitting }">
                        <span class="vb-book-btn-text" x-text="t('buttons.confirm')"></span>
                    </button>
                    <div class="vb-book-back-link">
                        <button type="button" class="vb-book-btn vb-book-btn-ghost" @click="goBack('details')" x-text="t('back.edit_details')"></button>
                    </div>

                    <!-- Cancellation policy disclosure -->
                    <template x-if="hasCancellationPolicy">
                        <div class="vb-book-policy-wrap">
                            <button type="button" class="vb-book-policy-toggle"
                                    x-bind:class="{ 'is-open': policyOpen }"
                                    @click="togglePolicy"
                                    x-bind:aria-expanded="policyOpen">
                                <svg class="vb-book-policy-chevron" viewBox="0 0 16 16" fill="none">
                                    <path d="M6 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span x-text="t('review.cancellation_policy_label')"></span>
                            </button>
                            <div class="vb-book-policy-text" x-show="policyOpen" x-transition
                                 x-text="cancellationPolicyText"></div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- ═══ Step 6: Confirmation ═══ -->
            <div x-show="isConfirmedStep" x-cloak class="vb-book-step" x-transition>
                <div class="vb-book-confirmation">
                    <div class="vb-book-checkmark-wrap">
                        <svg class="vb-book-checkmark" viewBox="0 0 64 64">
                            <circle class="vb-book-checkmark-circle" cx="32" cy="32" r="28"/>
                            <path class="vb-book-checkmark-check" d="M20 33 L28 41 L44 25"/>
                        </svg>
                    </div>
                    <div class="vb-book-confirm-heading"
                         x-text="eventIsWaitlisted ? t('event.waitlisted_title') : (bookingIsPending ? t('pending.heading') : t('confirmed.heading'))"></div>

                    <!-- Email-sent message (only when API confirms dispatch) -->
                    <div class="vb-book-confirm-message" x-show="eventIsWaitlisted"
                         x-text="t('event.waitlisted_message')"></div>
                    <div class="vb-book-confirm-message" x-show="bookingIsPending && !eventIsWaitlisted"
                         x-text="t('pending.message')"></div>
                    <div class="vb-book-confirm-message" x-show="!eventIsWaitlisted && !bookingIsPending && confirmEmailSent"
                         x-text="confirmEmailSent"></div>

                    <!-- Custom confirmation message (tenant-configurable) -->
                    <template x-if="confirmCustomMessage">
                        <div class="vb-book-confirm-message-custom" x-text="confirmCustomMessage"></div>
                    </template>

                    <template x-if="booking">
                        <div class="vb-book-confirm-ref" x-text="booking.id"></div>
                    </template>

                    <div class="vb-book-confirm-summary">
                        <div class="vb-book-summary">
                            <template x-for="row in activeConfirmRows" x-bind:key="row.label">
                                <div class="vb-book-summary-row">
                                    <span class="vb-book-summary-label" x-text="row.label"></span>
                                    <span class="vb-book-summary-value" x-text="row.value"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Primary calendar actions (hidden when no calendar data) -->
                    <template x-if="hasCalendarActions">
                        <div class="vb-book-confirm-actions">
                            <a x-bind:href="gcalUrl" target="_blank" rel="noopener" class="vb-book-btn vb-book-btn-secondary">
                                <i data-lucide="calendar" class="vb-book-btn-icon"></i>
                                <span class="vb-book-btn-text" x-text="t('buttons.add_to_calendar')"></span>
                            </a>
                            <button type="button" class="vb-book-btn vb-book-btn-secondary" @click="downloadIcs">
                                <i data-lucide="download" class="vb-book-btn-icon"></i>
                                <span class="vb-book-btn-text" x-text="t('buttons.download_ics')"></span>
                            </button>
                        </div>
                    </template>

                    <!-- Secondary actions: book another, reschedule, cancel -->
                    <div class="vb-book-confirm-actions-secondary">
                        <button type="button" class="vb-book-btn vb-book-btn-ghost" @click="bookAnother"
                                x-text="t('buttons.book_another')"></button>
                        <template x-if="showReschedule && booking">
                            <a x-bind:href="manageUrl(booking.id)" class="vb-book-btn vb-book-btn-ghost"
                               x-text="t('buttons.reschedule')"></a>
                        </template>
                        <template x-if="showCancel && booking">
                            <a x-bind:href="manageUrl(booking.id)" class="vb-book-btn vb-book-btn-ghost"
                               x-text="t('buttons.cancel_booking')"></a>
                        </template>
                    </div>
                </div>
            </div>

            <!-- ── Step: Manage Booking ── -->
            <div class="vb-book-step" x-show="isManageStep" x-transition>
                <div class="vb-book-manage-container">
                    <!-- Loading state -->
                    <template x-if="manageLoading">
                        <div class="vb-book-manage-loading">
                            <div class="vb-book-spinner"></div>
                            <p x-text="t('manage.loading')"></p>
                        </div>
                    </template>

                    <!-- Cancelled state -->
                    <template x-if="!manageLoading && manageCancelled">
                        <div class="vb-book-manage-cancelled">
                            <div class="vb-book-confirm-check vb-book-confirm-check-cancel">
                                <svg viewBox="0 0 52 52" class="vb-book-checkmark-svg is-cancel">
                                    <circle cx="26" cy="26" r="25" fill="none" class="vb-book-checkmark-circle is-cancel"/>
                                    <path fill="none" d="M16 16 L36 36 M36 16 L16 36" class="vb-book-checkmark-check is-cancel"/>
                                </svg>
                            </div>
                            <h2 class="vb-book-step-title" x-text="t('manage.cancelled_heading')"></h2>
                            <p class="vb-book-manage-message" x-text="t('manage.cancelled_message')"></p>
                            <div class="vb-book-confirm-actions-secondary" style="margin-top: 1.5rem;">
                                <a x-bind:href="bookingPageUrl" class="vb-book-btn vb-book-btn-brand"
                                   x-text="t('manage.book_again')"></a>
                            </div>
                        </div>
                    </template>

                    <!-- Active booking management -->
                    <template x-if="!manageLoading && managedBooking && !manageCancelled">
                        <div class="vb-book-manage-active">
                            <h2 class="vb-book-step-title" x-text="t('manage.heading')"></h2>

                            <!-- Status badge -->
                            <div class="vb-book-manage-status">
                                <span class="vb-book-manage-status-badge"
                                      x-bind:class="'is-' + managedBooking.status"
                                      x-text="manageStatusLabel"></span>
                            </div>

                            <!-- Booking details card -->
                            <div class="vb-book-confirm-summary">
                                <div class="vb-book-summary">
                                    <template x-for="row in manageSummaryRows" x-bind:key="row.label">
                                        <div class="vb-book-summary-row">
                                            <span class="vb-book-summary-label" x-text="row.label"></span>
                                            <span class="vb-book-summary-value" x-text="row.value"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Customer info -->
                            <template x-if="managedBooking.customer_name">
                                <div class="vb-book-manage-customer">
                                    <span x-text="managedBooking.customer_name"></span>
                                    <span class="vb-book-manage-customer-email" x-text="managedBooking.customer_email"></span>
                                </div>
                            </template>

                            <!-- Action buttons -->
                            <div class="vb-book-manage-actions">
                                <!-- Cancel button -->
                                <template x-if="manageCanCancel">
                                    <button type="button"
                                            class="vb-book-btn vb-book-btn-danger"
                                            @click="manageCancelModalOpen = true"
                                            x-text="t('buttons.cancel_booking')">
                                    </button>
                                </template>

                                <!-- Time gate message for cancel -->
                                <template x-if="!manageCanCancel && managedBooking.status === 'confirmed' && config.allow_cancellation">
                                    <p class="vb-book-manage-gate-msg" x-text="t('manage.time_gate_cancel')"></p>
                                </template>

                                <!-- Book another -->
                                <a x-bind:href="bookingPageUrl" class="vb-book-btn vb-book-btn-ghost"
                                   x-text="t('buttons.book_another')"></a>
                            </div>
                        </div>
                    </template>

                    <!-- Cancel confirmation modal -->
                    <div class="vb-book-modal-overlay" x-show="manageCancelModalOpen" x-transition.opacity>
                        <div class="vb-book-modal" @click.outside="manageCancelModalOpen = false">
                            <h3 class="vb-book-modal-title" x-text="t('manage.cancel_heading')"></h3>
                            <p class="vb-book-modal-body" x-text="t('manage.cancel_confirm')"></p>

                            <div class="vb-book-modal-field">
                                <label class="vb-book-label" x-text="t('manage.cancel_reason_label')"></label>
                                <textarea class="vb-book-input vb-book-textarea"
                                          rows="3"
                                          x-bind:placeholder="t('manage.cancel_reason_placeholder')"
                                          @input="setManageCancelReason($event.target.value)"></textarea>
                            </div>

                            <div class="vb-book-modal-actions">
                                <button type="button"
                                        class="vb-book-btn vb-book-btn-ghost"
                                        @click="manageCancelModalOpen = false"
                                        x-text="t('manage.cancel_nevermind')">
                                </button>
                                <button type="button"
                                        class="vb-book-btn vb-book-btn-danger"
                                        @click="cancelManagedBooking"
                                        x-bind:disabled="manageCancelling">
                                    <span x-show="!manageCancelling" x-text="t('manage.cancel_button')"></span>
                                    <span x-show="manageCancelling" class="vb-book-spinner-inline"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </main>

        <!-- ── Toast ── -->
        <div class="vb-book-toast-container" x-show="hasToast" x-transition>
            <template x-if="hasToast">
                <div class="vb-book-toast is-visible" x-bind:class="toastClass" role="alert">
                    <span class="vb-book-toast-message" x-text="toast.message"></span>
                    <button class="vb-book-toast-close" type="button" @click="dismissToast" aria-label="<?= __('booking.common.dismiss') ?>">
                        <i data-lucide="x"></i>
                    </button>
                </div>
            </template>
        </div>

        <!-- ── Theme Toggle ── -->
        <button type="button"
                class="vb-book-theme-toggle"
                @click="toggleTheme"
                x-bind:aria-label="isDark ? t('theme.switch_to_light') : t('theme.switch_to_dark')"
                x-bind:title="isDark ? t('theme.switch_to_light') : t('theme.switch_to_dark')">
            <svg x-show="isDark" x-cloak class="vb-book-theme-icon" x-bind:class="isDark ? 'is-visible' : 'is-hidden'"
                 data-lucide="sun"></svg>
            <svg x-show="!isDark" class="vb-book-theme-icon" x-bind:class="!isDark ? 'is-visible' : 'is-hidden'"
                 data-lucide="moon"></svg>
        </button>

        <!-- ── Footer ── -->
        <footer class="vb-book-footer" x-show="!isLoading">
            <span><?= __('booking.footer.powered_by') ?></span>
            <a href="<?= htmlspecialchars(brand_url(), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars(app_name(), ENT_QUOTES, 'UTF-8') ?></a>
        </footer>
    </div>

    <!-- Tenant config for JS -->
    <script>
        window.__VB_CONFIG__ = <?= json_encode($tenantConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        window.__VB_CSRF__ = <?= json_encode($csrfToken) ?>;
        window.__VB_TS__ = Date.now();
        window.__VB_I18N__ = <?= json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        window.__VB_FMT__ = <?= json_encode($formatting, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        <?php if (\App\Engine\DemoMode::isActive()): ?>
        window.VB_DEMO = true;
        window.__VB_DEMO_NOTICE__ = <?= json_encode(__('admin.demo.booking_notice')) ?>;
        <?php endif; ?>
    </script>
    <script type="module" src="/assets/js/booking.js"></script>
</body>
</html>
