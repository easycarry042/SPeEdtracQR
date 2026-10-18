{{-- Same keyword rules as the <x-request-type-icon> component, which serves
     the server-rendered lists. Kept in step by hand: the request tables on the
     dashboards and the super admin's Requests page are built in the browser
     from an Alpine payload, so they choose the glyph here. --}}
<script>
    window.REQUEST_TYPE_ICON_BASE = @json(asset('images/request-types'));

    window.requestTypeIcon = function (type) {
        const needle = (type || '').toLowerCase();
        const rules = [
            [['lei', 'flower', 'wreath'], 'color-triangle.svg'],
            [['plaza', 'hall', 'venue', 'court', 'gym', 'facility', 'building'], 'office-building.svg'],
            [['flag'], 'flag.png'],
            [['banner', 'tarpaulin', 'spider', 'streamer'], 'web.png'],
        ];

        for (const rule of rules) {
            if (rule[0].some(function (keyword) { return needle.includes(keyword); })) {
                return window.REQUEST_TYPE_ICON_BASE + '/' + rule[1];
            }
        }

        return window.REQUEST_TYPE_ICON_BASE + '/task-list-pen.svg';
    };
</script>
