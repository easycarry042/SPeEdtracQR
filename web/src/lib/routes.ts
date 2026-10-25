/**
 * Named routes, the way Blade called `route('track.show', …)`.
 *
 * Every path here is byte-identical to the Laravel URL it replaces. That is not
 * cosmetic: QR stickers already printed encode `/internal/track`, receipt and
 * reply emails already sent link to `/track/{number}`, and citizens have the
 * tracking page bookmarked. A renamed path silently breaks paper that is
 * already in people's hands.
 *
 * Citizen-facing links carry `?view=citizen` so a staff login on a shared
 * counter PC cannot divert a citizen to the staff desk (see `CitizenView`).
 */

/** Query parameters that mark a link as citizen-facing. */
export const CITIZEN_VIEW = { view: 'citizen' } as const;

type Params = Record<string, string | number | boolean | null | undefined>;

function withQuery(path: string, params?: Params): string {
  if (!params) {
    return path;
  }

  const search = new URLSearchParams();

  for (const [key, value] of Object.entries(params)) {
    if (value !== null && value !== undefined && value !== '') {
      search.set(key, String(value));
    }
  }

  const query = search.toString();

  return query === '' ? path : `${path}?${query}`;
}

const seg = (value: string | number): string => encodeURIComponent(String(value));

export const routes = {
  /* ── Public / citizen ─────────────────────────────────────────────────── */
  welcome: (q?: Params) => withQuery('/', q),
  home: () => '/home',
  privacy: () => '/privacy',

  /** The old citizen portal page — redirects to the landing page. */
  citizenDashboard: () => '/citizen',
  citizenTrack: () => '/citizen/track',

  publicRequestCreate: (q?: Params) => withQuery('/request', q),
  publicRequestStore: () => '/request',
  publicRequestSubmitted: (q?: Params) => withQuery('/request/submitted', q),

  trackIndex: (q?: Params) => withQuery('/track', q),
  trackLookup: (q?: Params) => withQuery('/track-lookup', q),
  trackSearch: (q?: Params) => withQuery('/track-search', q),
  /** The citizen tracking page. Always carries `?view=citizen`. */
  trackShow: (trackingNumber: string, q?: Params) =>
    withQuery(`/track/${seg(trackingNumber)}`, { ...CITIZEN_VIEW, ...q }),
  trackStatus: (trackingNumber: string) => `/track/${seg(trackingNumber)}/status`,
  trackSlip: (trackingNumber: string) => `/track/${seg(trackingNumber)}/slip`,
  trackAsk: (trackingNumber: string) => `/track/${seg(trackingNumber)}/ask`,
  trackMessagesIndex: (trackingNumber: string) => `/track/${seg(trackingNumber)}/messages`,
  trackMessagesStore: (trackingNumber: string) => `/track/${seg(trackingNumber)}/messages`,
  trackMessagesVerify: (trackingNumber: string) => `/track/${seg(trackingNumber)}/verify-contact`,
  trackMessagesAttachment: (commentId: number | string) =>
    `/track/messages/${seg(commentId)}/attachment`,
  trackCitizenUpload: (trackingNumber: string) => `/track/${seg(trackingNumber)}/upload`,
  trackRequirementReupload: (trackingNumber: string, requirementId: number | string) =>
    `/track/${seg(trackingNumber)}/requirements/${seg(requirementId)}/reupload`,

  verifyShow: (trackingNumber: string) => `/verify/${seg(trackingNumber)}`,

  /** Public internal-request tracker — what INT- QR images open. */
  internalTrack: (q?: Params) => withQuery('/internal-requests/track', q),

  speedyAsk: () => '/speedy/ask',
  speedyInbox: () => '/speedy/inbox',

  /* ── Auth ─────────────────────────────────────────────────────────────── */
  login: (q?: Params) => withQuery('/login', q),
  logout: () => '/logout',
  passwordRequest: () => '/forgot-password',
  passwordEmail: () => '/forgot-password',
  passwordReset: (token: string) => `/reset-password/${seg(token)}`,
  passwordStore: () => '/reset-password',
  passwordConfirm: () => '/confirm-password',
  passwordUpdate: () => '/password',
  verificationNotice: () => '/verify-email',
  verificationSend: () => '/email/verification-notification',

  /* ── Role landings ────────────────────────────────────────────────────── */
  /** Supervisor desk. */
  dashboard: (q?: Params) => withQuery('/dashboard', q),
  /** Staff cockpit. */
  staffDashboard: (q?: Params) => withQuery('/my-dashboard', q),
  /** Super admin command center. */
  adminDashboard: (q?: Params) => withQuery('/admin/dashboard', q),
  adminDashboardExport: (q?: Params) => withQuery('/admin/dashboard/export', q),

  /* ── Documents (citizen requests) ─────────────────────────────────────── */
  documentsCreate: () => '/documents/create',
  documentsStore: () => '/documents',
  documentsCreated: (documentId: number | string) => `/documents/${seg(documentId)}/created`,
  documentsEdit: (documentId: number | string) => `/documents/${seg(documentId)}/edit`,
  documentsUpdate: (documentId: number | string) => `/documents/${seg(documentId)}`,
  documentsAccept: (documentId: number | string) => `/documents/${seg(documentId)}/accept`,
  documentsDeny: (documentId: number | string) => `/documents/${seg(documentId)}/deny`,
  documentsRelease: (documentId: number | string) => `/documents/${seg(documentId)}/release`,
  documentsSticker: (documentId: number | string) => `/documents/${seg(documentId)}/sticker`,
  documentsClaimDate: (documentId: number | string) => `/documents/${seg(documentId)}/claim-date`,
  documentsAssignApprove: (documentId: number | string) =>
    `/documents/${seg(documentId)}/assign-approve`,
  documentsComplete: (trackingNumber: string) => `/documents/${seg(trackingNumber)}/complete`,

  documentsStatusSet: (documentId: number | string) => `/documents/${seg(documentId)}/status`,
  documentsStatusAdvance: (documentId: number | string) =>
    `/documents/${seg(documentId)}/status/advance`,
  documentsStatusRevert: (documentId: number | string) =>
    `/documents/${seg(documentId)}/status/revert`,
  documentsStatusHold: (documentId: number | string) => `/documents/${seg(documentId)}/hold`,
  documentsStatusUnhold: (documentId: number | string) => `/documents/${seg(documentId)}/unhold`,

  documentsAssignmentAccept: (documentId: number | string) =>
    `/documents/${seg(documentId)}/assignment/accept`,
  documentsAssignmentDecline: (documentId: number | string) =>
    `/documents/${seg(documentId)}/assignment/decline`,
  documentsAssignmentRevision: (documentId: number | string) =>
    `/documents/${seg(documentId)}/assignment/revision`,

  documentsAttachmentsStore: (documentId: number | string) =>
    `/documents/${seg(documentId)}/attachments`,
  documentsCommentsStore: (documentId: number | string) => `/documents/${seg(documentId)}/comments`,
  documentsCommentsRead: (documentId: number | string) =>
    `/documents/${seg(documentId)}/comments/read`,
  documentsCommentsAttachment: (commentId: number | string) =>
    `/messages/${seg(commentId)}/attachment`,

  documentsRequirementsFile: (documentId: number | string, requirementId: number | string) =>
    `/documents/${seg(documentId)}/requirements/${seg(requirementId)}/file`,
  documentsRequirementsReview: (documentId: number | string, requirementId: number | string) =>
    `/documents/${seg(documentId)}/requirements/${seg(requirementId)}/review`,
  documentsRequirementsToggle: (documentId: number | string, requirementId: number | string) =>
    `/documents/${seg(documentId)}/requirements/${seg(requirementId)}/verify`,

  documentsReviewOpen: (documentId: number | string) => `/documents/${seg(documentId)}/review/open`,
  documentsReviewComplete: (documentId: number | string) =>
    `/documents/${seg(documentId)}/review/complete`,

  documentsCustodyStore: (documentId: number | string) => `/documents/${seg(documentId)}/custody`,
  /** One scan receives: posts an INT- code straight to its receipt. */
  documentsCustodyScan: () => '/custody/scan',

  /* ── Internal (office-to-office) requests ─────────────────────────────── */
  requestsIndex: (q?: Params) => withQuery('/requests', q),
  requestsCreate: (q?: Params) => withQuery('/requests/create', q),
  requestsStore: () => '/requests',
  requestsShow: (documentId: number | string, q?: Params) =>
    withQuery(`/requests/${seg(documentId)}`, q),
  requestsCreated: (documentId: number | string) => `/requests/${seg(documentId)}/created`,
  requestsRecipients: (departmentId: number | string) => `/requests/recipients/${seg(departmentId)}`,
  requestsQrStamp: (documentId: number | string) => `/requests/${seg(documentId)}/qr-stamp`,
  requestsResend: (documentId: number | string) => `/requests/${seg(documentId)}/resend`,
  requestsWithdraw: (documentId: number | string) => `/requests/${seg(documentId)}/withdraw`,
  requestsStepsApprove: (documentId: number | string) => `/requests/${seg(documentId)}/steps/approve`,
  requestsStepsReturn: (documentId: number | string) => `/requests/${seg(documentId)}/steps/return`,
  requestsStepsDeny: (documentId: number | string) => `/requests/${seg(documentId)}/steps/deny`,
  requestsStepsComplete: (documentId: number | string) =>
    `/requests/${seg(documentId)}/steps/complete`,
  requestsStepsSignature: (stepId: number | string) => `/request-steps/${seg(stepId)}/signature`,

  /* ── Scan / look up ──────────────────────────────────────────────────── */
  scanIndex: (q?: Params) => withQuery('/scan', q),

  /* ── History & reports ───────────────────────────────────────────────── */
  history: (q?: Params) => withQuery('/history', q),
  historyData: (q?: Params) => withQuery('/history/data', q),
  historyExport: (q?: Params) => withQuery('/history/export', q),
  /** Supervisor Reports — absorbed the old Analytics page and Services report. */
  reportsIndex: (q?: Params) => withQuery('/reports', q),
  reportsExport: (q?: Params) => withQuery('/reports/export', q),
  reportsNudge: (documentId: number | string) => `/reports/nudge/${seg(documentId)}`,
  /** Both redirect to `/reports`; kept so old links and bookmarks resolve. */
  analytics: (q?: Params) => withQuery('/analytics', q),
  reportsServices: (q?: Params) => withQuery('/reports/services', q),

  /* ── Resources: lending desk + stock ─────────────────────────────────── */
  inventoryIndex: (q?: Params) => withQuery('/inventory', q),
  inventoryUpdate: (resourceId: number | string) => `/inventory/${seg(resourceId)}`,
  inventoryLoansApprove: (bookingId: number | string) =>
    `/inventory/loans/${seg(bookingId)}/approve`,
  inventoryLoansCancel: (bookingId: number | string) => `/inventory/loans/${seg(bookingId)}/cancel`,
  inventoryLoansExtend: (bookingId: number | string) => `/inventory/loans/${seg(bookingId)}/extend`,
  inventoryLoansRelease: (bookingId: number | string) =>
    `/inventory/loans/${seg(bookingId)}/release`,
  inventoryLoansRemind: (bookingId: number | string) => `/inventory/loans/${seg(bookingId)}/remind`,
  inventoryLoansReturn: (bookingId: number | string) => `/inventory/loans/${seg(bookingId)}/return`,

  /* ── Booking calendar ────────────────────────────────────────────────── */
  bookingsIndex: (q?: Params) => withQuery('/bookings', q),
  bookingsApprove: (bookingId: number | string) => `/bookings/${seg(bookingId)}/approve`,
  bookingsCancel: (bookingId: number | string) => `/bookings/${seg(bookingId)}/cancel`,
  bookingsReschedule: (bookingId: number | string) => `/bookings/${seg(bookingId)}/reschedule`,

  /* ── Staff directory & profile ───────────────────────────────────────── */
  staffIndex: (q?: Params) => withQuery('/staff', q),
  staffSearch: (q?: Params) => withQuery('/staff/search', q),
  staffProfile: (userId: number | string, q?: Params) => withQuery(`/staff/${seg(userId)}`, q),
  staffAvatar: (userId: number | string) => `/staff/${seg(userId)}/avatar`,
  staffHighlightsStore: () => '/staff/highlights',

  profileEdit: (q?: Params) => withQuery('/profile', q),
  profileUpdate: () => '/profile',
  profileAvatarStore: () => '/profile/avatar',
  profileAvatarDestroy: () => '/profile/avatar',
  profileBadge: () => '/profile/badge',
  profileBadgeRegenerate: () => '/profile/badge/regenerate',
  profileSignatureShow: () => '/profile/signature',
  profileSignatureStore: () => '/profile/signature',
  profileSignatureDestroy: () => '/profile/signature',
  profileAvailabilityToggle: () => '/profile/availability',

  /* ── Notifications & nav ─────────────────────────────────────────────── */
  navBadges: () => '/nav/badges',
  notificationsOpen: (notificationId: string) => `/notifications/${seg(notificationId)}/open`,
  notificationsReadAll: () => '/notifications/read-all',

  /* ── Attachments ─────────────────────────────────────────────────────── */
  attachmentsShow: (attachmentId: number | string) => `/attachments/${seg(attachmentId)}`,
  attachmentsDestroy: (attachmentId: number | string) => `/attachments/${seg(attachmentId)}`,
  attachmentsEdit: (attachmentId: number | string) => `/attachments/${seg(attachmentId)}/edit`,
  attachmentsEditStore: (attachmentId: number | string) => `/attachments/${seg(attachmentId)}/edit`,

  /* ── Super admin: oversight & setup ──────────────────────────────────── */
  adminRequestsIndex: (q?: Params) => withQuery('/admin/requests', q),
  adminAuditLog: (q?: Params) => withQuery('/admin/audit-log', q),
  adminNudge: (documentId: number | string) => `/admin/nudge/${seg(documentId)}`,

  /** Assignment now happens in the Review modal; these stay for old links. */
  adminAssignmentsIndex: (q?: Params) => withQuery('/admin/assignments', q),
  adminAssignmentsUnclaimed: (q?: Params) => withQuery('/admin/assignments/unclaimed', q),
  adminAssignmentsAssign: (documentId: number | string) => `/admin/assignments/${seg(documentId)}`,

  adminUsersIndex: (q?: Params) => withQuery('/admin/users', q),
  adminUsersCreate: () => '/admin/users/create',
  adminUsersStore: () => '/admin/users',
  adminUsersEdit: (userId: number | string) => `/admin/users/${seg(userId)}/edit`,
  adminUsersUpdate: (userId: number | string) => `/admin/users/${seg(userId)}`,
  adminUsersDestroy: (userId: number | string) => `/admin/users/${seg(userId)}`,
  adminUsersArchive: (userId: number | string) => `/admin/users/${seg(userId)}/archive`,
  adminUsersRestore: (userId: number | string) => `/admin/users/${seg(userId)}/restore`,
  adminUsersToggleActive: (userId: number | string) => `/admin/users/${seg(userId)}/toggle-active`,
  adminUsersToggleAvailability: (userId: number | string) => `/admin/users/${seg(userId)}/availability`,
  adminUsersBulk: () => '/admin/users/bulk',

  adminDepartmentsIndex: (q?: Params) => withQuery('/admin/departments', q),
  adminDepartmentsCreate: () => '/admin/departments/create',
  adminDepartmentsStore: () => '/admin/departments',
  adminDepartmentsEdit: (departmentId: number | string) =>
    `/admin/departments/${seg(departmentId)}/edit`,
  adminDepartmentsUpdate: (departmentId: number | string) => `/admin/departments/${seg(departmentId)}`,
  adminDepartmentsToggleActive: (departmentId: number | string) =>
    `/admin/departments/${seg(departmentId)}/toggle-active`,

  adminRequestTypesIndex: (q?: Params) => withQuery('/admin/request-types', q),
  adminRequestTypesCreate: () => '/admin/request-types/create',
  adminRequestTypesStore: () => '/admin/request-types',
  adminRequestTypesEdit: (typeId: number | string) => `/admin/request-types/${seg(typeId)}/edit`,
  adminRequestTypesUpdate: (typeId: number | string) => `/admin/request-types/${seg(typeId)}`,
  adminRequestTypesDestroy: (typeId: number | string) => `/admin/request-types/${seg(typeId)}`,
  adminRequestTypesToggleActive: (typeId: number | string) =>
    `/admin/request-types/${seg(typeId)}/toggle-active`,

  adminRouteTemplatesIndex: (q?: Params) => withQuery('/admin/route-templates', q),
  adminRouteTemplatesCreate: () => '/admin/route-templates/create',
  adminRouteTemplatesStore: () => '/admin/route-templates',
  adminRouteTemplatesEdit: (templateId: number | string) =>
    `/admin/route-templates/${seg(templateId)}/edit`,
  adminRouteTemplatesUpdate: (templateId: number | string) =>
    `/admin/route-templates/${seg(templateId)}`,
  adminRouteTemplatesDestroy: (templateId: number | string) =>
    `/admin/route-templates/${seg(templateId)}`,
  adminRouteTemplatesToggleActive: (templateId: number | string) =>
    `/admin/route-templates/${seg(templateId)}/toggle-active`,

  /** Super admins get the shared Resources page; this redirects there. */
  adminResourcesIndex: (q?: Params) => withQuery('/admin/resources', q),
  adminResourcesCreate: () => '/admin/resources/create',
  adminResourcesEdit: (resourceId: number | string) => `/admin/resources/${seg(resourceId)}/edit`,
  adminResourcesStore: () => '/admin/resources',
  adminResourcesUpdate: (resourceId: number | string) => `/admin/resources/${seg(resourceId)}`,
  adminResourcesDestroy: (resourceId: number | string) => `/admin/resources/${seg(resourceId)}`,
  adminResourcesToggleActive: (resourceId: number | string) =>
    `/admin/resources/${seg(resourceId)}/toggle-active`,

  /* ── Storage & streams ───────────────────────────────────────────────── */
  storage: (path: string) => `/storage/${path.split('/').map(seg).join('/')}`,

  /** SSE endpoints replacing the Reverb channels. */
  streamDocument: (trackingNumber: string) => `/api/stream/document/${seg(trackingNumber)}`,
  streamStaff: (documentId: number | string) => `/api/stream/staff/${seg(documentId)}`,
  streamNotifications: () => '/api/stream/notifications',
} as const;

/**
 * Where a signed-in user belongs after login — the role landings the nav IA
 * settled on. Super admins get the command center, supervisors their office
 * desk, everyone else the staff cockpit.
 */
export function landingFor(actor: {
  isSuperAdmin(): boolean;
  isSupervisor(): boolean;
  isClerk?(): boolean;
}): string {
  if (actor.isSuperAdmin()) {
    return routes.adminDashboard();
  }

  // Action Mamamayan's Clerk only ever handles internal requests.
  if (actor.isClerk?.()) {
    return routes.requestsIndex();
  }

  return actor.isSupervisor() ? routes.dashboard() : routes.staffDashboard();
}
