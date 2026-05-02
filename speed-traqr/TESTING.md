# SPeED TraQR UI + Flow Testing Checklist

## Step 1 — New Submission
- Visit `/documents/create`
- Fill in document type, citizen name, category
- Click Submit
- PASS if: redirected to success page showing the QR code image and the tracking number

## Step 2 — QR Code File Check
- After step 1, check:
- `storage/app/public/qrcodes/SPD-XXXXXXXX-XXXXX.png`
- PASS if: file exists and image is a valid QR code

## Step 3 — Scan IN
- Visit `/scan`
- Select department, set action to IN
- Manually type the tracking number from step 1 and submit
- PASS if: green success alert appears and `document_scans` table has a new row with `action=in`

## Step 4 — Track Public
- Open private/incognito browser window (no login)
- Visit `/track`
- Enter tracking number from step 1
- PASS if: page shows status `In Transit`, current department name, and the scan log entry from step 3

## Step 5 — Scan OUT
- Return to `/scan`
- Select same department, set action to OUT
- Submit the same tracking number
- PASS if: success alert appears and system suggests next department from `routing_rules`

## Step 6 — Dashboard Check
- Visit `/dashboard`
- PASS if: Total Request count increased and new document appears in Recent Activity table

## Step 7 — History Check
- Visit `/history`
- PASS if: document from step 1 appears in table

## Step 8 — Analytics Check
- Visit `/analytics`
- PASS if: chart shows data and department from scan appears in Top Submitting Departments

## Step 9 — Offline Scan
- Go to `/scan`
- Open browser DevTools and set Network to Offline
- Submit a scan manually
- PASS if: offline badge appears with count = 1
- Set network back to Online
- PASS if: badge disappears and scan appears in database

## Step 10 — Full 3-Department Flow
- Create a new Business Permit document
- Scan IN at Zoning Office → verify status = `in_transit`
- Scan OUT at Zoning Office → verify suggestion = Treasury Office
- Scan IN at Treasury Office
- Scan OUT at Treasury Office → verify suggestion = Mayor's Office
- Scan IN at Mayor's Office
- Scan OUT at Mayor's Office → verify status = `completed`
- Check public tracking page
- PASS if: full timeline shows all 6 events
