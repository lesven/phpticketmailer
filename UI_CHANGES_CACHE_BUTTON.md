# UI Changes - Cache Clear Button

## Dashboard Page Changes

### Before
The statistics section had only a heading:
```html
<h2 class="mb-3">E-Mail-Statistiken</h2>
```

### After
The statistics section now includes a "Cache löschen" button:
```html
<h2 class="mb-3">
    E-Mail-Statistiken
    <a href="{{ path('cache_clear') }}" class="btn btn-sm btn-outline-secondary float-end" title="Statistik-Cache löschen">
        <i class="fas fa-sync-alt"></i> Cache löschen
    </a>
</h2>
```

## Visual Description

The button appears:
- **Location:** In the statistics section heading, aligned to the right (float-end)
- **Style:** Small outline secondary button (btn-sm btn-outline-secondary)
- **Icon:** Sync/refresh icon (fas fa-sync-alt)
- **Text:** "Cache löschen" (German for "Clear Cache")
- **Tooltip:** "Statistik-Cache löschen" on hover

## User Flow

1. User navigates to the Dashboard (/)
2. User sees the "Cache löschen" button in the statistics section
3. User clicks the button
4. System clears all statistics cache
5. System redirects back to dashboard
6. Success message is displayed: "Statistik-Cache wurde erfolgreich gelöscht"
7. Next statistics load will fetch fresh data from the database

## Implementation Details

- **Route:** `/cache/clear` (named route: `cache_clear`)
- **HTTP Method:** GET
- **Controller Action:** `DashboardController::clearCache()`
- **Response:** 302 redirect to dashboard with flash message

## Cache Behavior

### Automatic Cache Clearing
The cache is automatically cleared when:
- A CSV file is uploaded and processed
- This happens in `CsvUploadOrchestrator::processUpload()`

### Manual Cache Clearing
Users can manually clear the cache by:
- Clicking the "Cache löschen" button on the dashboard
- This is useful for:
  - Testing
  - Forcing a refresh of statistics
  - Resolving potential cache inconsistencies

### Cache Duration
- Default TTL: 1 hour (3600 seconds)
- Cache keys include the month parameter (e.g., `statistics.monthly_user_by_domain_6`)
- Separate caches for user statistics and ticket statistics
