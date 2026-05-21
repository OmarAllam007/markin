Missing Features (Gaps in Existing Domain)
1. Leave Management Module
The Leave attendance status and OnLeave employee status exist but there's no leave request/approval workflow. Missing:

Leave types (annual, sick, emergency, unpaid)
Employee leave balance tracking
Request → Manager approval → HR approval pipeline
Leave calendar view
Integration with attendance calculation (auto-mark as Leave instead of Absent)


2. Holiday Calendar Management
Holiday attendance status exists but no holiday master data. Missing:

Define public holidays per year
Country/region-specific holidays (multi-tenant already has timezone)
Auto-apply holiday status on attendance calculation
3. Payroll Export / Integration
payroll_exported_at field is on the Attendance model but no payroll module. Missing:

Payroll period configuration
Deduction/bonus rules based on attendance (late deductions, overtime pay)
Export to Excel/CSV for payroll systems
Mark records as exported to prevent double-processing
4. Business Trip Management
BusinessTrip attendance status exists but no trip management. Missing:

Trip request/approval
Per diem tracking
Auto-mark attendance as BusinessTrip for the trip duration
Incomplete Features (UI/Workflow Gaps)
5. ZK Machine Raw Log Viewer
ZkRawLog model and ZkPushController exist but no UI to view sync status or raw logs. The ZK Machines index page likely has no way to inspect what was synced or debug failed punches.

6. Employee Document Management
Employee has id_number, social_security_number, contract_type, contract_end_date fields, suggesting documents exist, but no document upload/management UI — no way to attach ID scans, contracts, certificates.

7. Contract Expiry Alerts
contract_end_date is stored but no alerting system for expiring contracts. A dashboard widget or automated email/notification when contracts expire within X days would be valuable.

8. Overtime Approval Workflow
AttendanceController::approve exists for attendance approval but overtime has no separate approval step — overtime is auto-calculated. A dedicated overtime approval flow (employee submits, manager approves) is a common requirement.

New Features Worth Adding
9. Employee Self-Service Portal
Currently the system is admin-only. Employees authenticate via mobile API (OTP) but there's no web portal for employees to:

View their own attendance history
Submit attendance corrections
Request leaves
View announcements targeted at them
10. Automated Notifications (Email/SMS)
CompanySetting has send_reminders flag but no notification infrastructure. Candidates:

Daily late arrival alerts to managers
Absence alerts after X consecutive days
Contract expiry reminders
Shift reminder to employees before check-in time
11. Shift Scheduling / Roster
Shifts are assigned statically to employees but there's no scheduling calendar. A roster feature would allow:

Assign different shifts per week/day
Temporary shift assignments (the allow_temporary_shifts setting exists already)
Manager-visible schedule view
12. Two-Factor Authentication (2FA) for Admin Users
No 2FA exists for the web admin panel — important for an HR system handling sensitive employee data.

13. Audit Log / Activity History
Manual attendance edits track is_manual_edit and edit_reason, but there's no system-wide audit trail showing who changed what across all entities (employees, shifts, settings, etc.).

14. Advanced HR Analytics
Current dashboard has KPI cards and charts, but missing:

Turnover rate tracking
Absenteeism trends by department
Late arrival heatmap per employee/department
Overtime cost analysis
Predictive alerts ("Department X has 30% absence rate this week")
15. Bulk Attendance Operations
Only employee CSV import exists. Missing bulk operations:

Bulk approve/lock attendance records
Bulk manual attendance creation for a group (e.g., mark all as Holiday for a date range)
Bulk export attendance for a specific period/department
16. Location Geofencing Visualization
LocationCheckController validates GPS radius for mobile punches, but no map UI to:

Draw the geofence boundary on a map
View where employees actually punched in (heatmap of GPS coordinates)
Flag suspicious punch locations
Priority Recommendation
Priority	Feature	Reason
High	Leave Management	Attendance system is incomplete without it
High	Holiday Calendar	Attendance statuses depend on it
High	Contract Expiry Alerts	Data already exists, just needs automation
Medium	Payroll Export	payroll_exported_at field suggests it was planned
Medium	Employee Self-Service	Significant usability gap
Medium	Shift Scheduling/Roster	allow_temporary_shifts setting hints at this need
Low	2FA	Security hardening
Low	Audit Log	Compliance requirement for enterprise
The Leave Management + Holiday Calendar pair is the most glaring gap — the attendance engine already accounts for both statuses but has no way to populate them systematically.