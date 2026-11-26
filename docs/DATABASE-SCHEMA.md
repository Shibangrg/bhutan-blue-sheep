Database Schema Specification
Project: Bhutan Blue Sheep Tours & Travels
Document: Database Schema Specification
Status: Revised logical schema, pending environment compatibility confirmation
Version: 0.2
Last Updated: 9 November 2026
1. Purpose
   This document translates the approved database architecture into a detailed logical schema specification for the Blue Sheep Business Manager WordPress plugin.
It defines the proposed tables, columns, data types, keys, relationships, indexes, and data integrity requirements.

This document is separate from DATABASE-ARCHITECTURE.md. The approved architecture remains unchanged.

No database tables will be created until this specification has been reviewed and approved.
2. Database Conventions
2.1 Table Naming
- Use the WordPress database prefix followed by bs_ and the table name.
- Example: {wordpress_prefix}bs_customers.
- Never assume the WordPress prefix is wp_.
  2.2 General Column Conventions
- Use BIGINT UNSIGNED with auto-increment for every custom-table primary key.
- Use BIGINT UNSIGNED for custom-table foreign-key columns that reference those primary keys.
- WordPress user IDs and WordPress post IDs are external WordPress references. Match their actual WordPress column types and validate them against WordPress records rather than assuming they are custom-table foreign keys.
- Use created_at and updated_at for record timestamps where applicable.
- Store timestamps in UTC using a consistent database representation.
- Use the configured WordPress timezone when displaying dates and times.
- Use fixed-precision decimal types for monetary values.
- Do not use floating-point types for financial calculations.
- Use application-level validation for status values and permitted transitions.
- Do not use customer email addresses as unique identifiers.
- Use restrictive deletion behavior for business and financial history. Do not cascade-delete bookings, payments, accepted quotation versions, or audit records.
  2.3 Compatibility
  The current environments differ:
- Local development: MySQL 8.4.0.
- Existing production environment: MariaDB 10.0.32.
  The production database version is old and is not yet approved as the implementation target. Before writing migrations, confirm the actual hosting provider's supported database versions and decide whether production can be upgraded. If MariaDB 10.0.32 must remain the target, test every data type, index, constraint, character set, collation, and migration statement against that exact version.
Until that decision and compatibility testing are complete, this document defines the logical schema, not production-ready SQL DDL. Do not create or alter production tables based solely on the local MySQL environment.
3. Customer Table
Table: {wordpress_prefix}bs_customers

Purpose: Stores the minimum customer information needed to manage enquiries, quotations, bookings, and related business records.
Column	Logical data type	Required	Description
id	BIGINT UNSIGNED, auto-increment	Yes	Primary key
full_name	VARCHAR(190)	Yes	Customer's full name
email	VARCHAR(254)	Yes	Customer email address
phone	VARCHAR(50)	No	Contact phone number
country	VARCHAR(100)	No	Customer's country
created_at	UTC timestamp	Yes	Record creation time
updated_at	UTC timestamp	Yes	Last record update time

3.1 Keys and Indexes
- Primary key: id.
- Index: email.
- Do not impose a unique constraint on email, since multiple customers may share an email address.
  3.2 Data Integrity Rules
- A customer must have a name and email address.
- Email addresses must be validated by the application.
- Customer records linked to bookings or financial records must not be physically deleted.
- Customer data must follow the approved retention and anonymization policy.
4. Enquiry Table
   Table: {wordpress_prefix}bs_enquiries
Purpose: Stores custom trip enquiries and fixed-package reservation requests submitted by prospective customers.
Column	Logical data type	Required	Description
id	BIGINT UNSIGNED, auto-increment	Yes	Primary key
customer_id	BIGINT UNSIGNED	Yes	References bs_customers.id
enquiry_type	VARCHAR(30)	Yes	One of custom_trip, package_reservation, or general
tour_id	BIGINT UNSIGNED	No	Logical reference to the relevant WordPress tour post ID; validate against WordPress posts
preferred_start_date	DATE	No	Preferred travel start date
preferred_end_date	DATE	No	Preferred travel end date
dates_flexible	BOOLEAN	Yes	Indicates whether travel dates are flexible
traveller_count	BIGINT UNSIGNED	No	Total number of travellers; required for custom_trip and package_reservation, optional for general
adults_count	BIGINT UNSIGNED	No	Number of adult travellers
children_count	BIGINT UNSIGNED	No	Number of child travellers
destinations	Validated JSON document stored in a compatibility-appropriate text column	No	Requested destinations
interests	Validated JSON document stored in a compatibility-appropriate text column	No	Customer interests
budget_range	VARCHAR(100)	No	Optional customer budget range
additional_requirements	TEXT	No	Additional customer requirements
status	VARCHAR(40)	Yes	Current enquiry status
created_at	UTC timestamp	Yes	Record creation time
updated_at	UTC timestamp	Yes	Last record update time

4.1 Keys and Indexes
- Primary key: id.
- Foreign key: customer_id references bs_customers.id.
- tour_id is a logical reference to a WordPress tour post ID and must be validated against the WordPress posts table. Do not create a custom-table foreign key to a WordPress post unless the implementation explicitly establishes and tests that relationship.
- Index: customer_id.
- Indexes should be selected based on actual application queries. Do not automatically add an ordinary index to every low-cardinality status field.
- A composite index such as (status, created_at) may be more useful for the enquiry dashboard than separate indexes, depending on measured queries.
- Index: tour_id where package/tour lookups are used.
  4.2 Data Integrity Rules
- Every enquiry must belong to a customer.
- enquiry_type must be one of custom_trip, package_reservation, or general.
- traveller_count is required and must be greater than zero for custom_trip and package_reservation enquiries. It may be null for general enquiries.
- Adult and child counts, when supplied, must be zero or greater.
- When traveller_count and both adult and child counts are supplied, their sum must equal traveller_count.
- For a general enquiry, trip-specific fields may remain null.
- A package reservation enquiry should reference the relevant WordPress tour post through tour_id where applicable.
- The preferred end date must not be earlier than the preferred start date.
- Flexible dates must be represented explicitly.
- Destinations and interests must be validated before storage.
- Enquiry status changes must follow the approved transition rules.
- Enquiries linked to quotations or bookings must be preserved according to the retention policy.
5. Quotation Table
   Table: {wordpress_prefix}bs_quotations
Purpose: Stores the main quotation record and links it to the originating enquiry and customer. Individual quotation revisions are stored separately in the quotation versions table.
Column	Logical data type	Required	Description
id	BIGINT UNSIGNED, auto-increment	Yes	Primary key
enquiry_id	BIGINT UNSIGNED	Yes	References bs_enquiries.id
customer_id	BIGINT UNSIGNED	Yes	References bs_customers.id
reference_number	VARCHAR(50)	Yes	Unique customer-facing quotation reference
current_version_id	BIGINT UNSIGNED	No	References the current quotation version in bs_quotation_versions
status	VARCHAR(40)	Yes	Current quotation status
created_by	BIGINT UNSIGNED	Yes	WordPress user ID of the creator; logical WordPress reference
created_at	UTC timestamp	Yes	Record creation time
updated_at	UTC timestamp	Yes	Last record update time

5.1 Keys and Indexes
- Primary key: id.
- Foreign key: enquiry_id references bs_enquiries.id.
- Foreign key: customer_id references bs_customers.id.
- current_version_id references the selected record in bs_quotation_versions.
- Because the quotation and version tables refer to each other, create the quotation first with current_version_id null, create its first quotation version, then update current_version_id. Perform the related application operations transactionally where supported.
- Validate that the selected version's quotation_id equals the parent quotation's id. A simple foreign key on current_version_id alone cannot enforce this same-parent condition.
- created_by references the responsible WordPress user logically and must be validated against WordPress users.
- Unique index: reference_number.
- Index: enquiry_id.
- Index: customer_id.
- Index: status.
- Index: created_at.
- Index: current_version_id.
  5.2 Data Integrity Rules
- Every quotation must belong to an existing customer and enquiry.
- The customer associated with a quotation must match the customer associated with its enquiry.
- Each quotation must have a unique reference number.
- A quotation may contain multiple versions.
- Only one version may be designated as the current version at a time.
- On initial creation, insert the quotation with a null current_version_id, insert its first draft version, then set current_version_id to that version.
- When changing the current version, validate parent ownership and update the current-version pointer and master status consistently. Use a transaction and appropriate row-level locking where supported.
- The quotation master status must always reflect the status of the version referenced by current_version_id.
- When the current quotation version changes, the application must update current_version_id and the quotation master status consistently.
- A quotation must not reference a current version belonging to a different quotation.
- Historical quotation records must be preserved.
- Deleting a customer or enquiry must not automatically delete associated quotations.
6. Quotation Version Table
   Table: {wordpress_prefix}bs_quotation_versions
Purpose: Stores individual versions of a quotation, including itinerary details, pricing, currency, deposit requirements, validity, and agreed terms.

Each revision creates a new version. Previously sent or accepted versions must remain unchanged.
Column	Logical data type	Required	Description
id	BIGINT UNSIGNED, auto-increment	Yes	Primary key
quotation_id	BIGINT UNSIGNED	Yes	References bs_quotations.id
version_number	BIGINT UNSIGNED	Yes	Sequential version number within the quotation
currency	CHAR(3)	Yes	Quotation currency code
base_currency	CHAR(3)	No	Internal reference currency, normally BTN
exchange_rate	DECIMAL(20,10)	No	Exchange rate used for currency conversion
exchange_rate_source	VARCHAR(190)	No	Source of the exchange rate
exchange_rate_date	DATE	No	Date on which the exchange rate was applied
subtotal	DECIMAL(19,4)	Yes	Total before discount
discount	DECIMAL(19,4)	Yes	Discount amount
total_amount	DECIMAL(19,4)	Yes	Final quotation amount
deposit_type	VARCHAR(20)	No	Fixed amount or percentage
deposit_value	DECIMAL(19,4)	No	Deposit amount or percentage value
balance_due_date	DATE	No	Date by which the remaining balance is due
cancellation_terms	TEXT	No	Cancellation and refund conditions
terms_and_conditions	TEXT	No	Terms applicable to this quotation version
valid_until	UTC timestamp	No	Date and time at which this quotation version expires
accepted_at	UTC timestamp	No	Time at which the customer accepted this version
acceptance_method	VARCHAR(40)	No	Method used to record acceptance, such as secure_link, email, or administrator_recorded
acceptance_reference	VARCHAR(255)	No	Optional protected reference to acceptance evidence; must not contain a raw access token or public document URL
created_at	UTC timestamp	Yes	Version creation time
status	VARCHAR(40)	Yes	Current status of this quotation version, using the approved version-level statuses

6.1 Keys and Indexes
- Primary key: id.
- Foreign key: quotation_id references bs_quotations.id.
- Unique composite index: (quotation_id, version_number).
- Index: quotation_id.
- Index: valid_until.
- Index: accepted_at.
- Index: status.
  6.2 Data Integrity Rules
- Every version must belong to an existing quotation.
- Version numbers must be unique within their quotation.
- Version numbering must increase sequentially.
- Monetary values must use fixed-precision decimal storage.
- All monetary values within a version must use the currency specified by currency.
- base_currency identifies the reference currency used for conversion.
- Exchange-rate direction must be quotation-currency units per one unit of base currency.
- Exchange-rate fields may be empty when no currency conversion is required.
- Historical quotation versions must retain their original currency and exchange-rate information.
- Historical quotation totals must never be recalculated using a later exchange rate.
- The deposit value must follow the selected deposit type.
- The balance due date must be consistent with the agreed payment terms.
- A version must not be accepted after its validity period has expired.
- Once a quotation version has been sent, its pricing, itinerary, items, and terms must not be modified.
- Any requested revision must create a new quotation version.
- An accepted version must remain permanently identifiable as the version accepted by the customer.
- Only one version may be accepted for a quotation unless an explicit business process is approved otherwise.
  6.3 Quotation Version Status Values
  Use these seven application-level status values:
  Stored value	Display label	Meaning
  draft	Draft	The version is being prepared and has not been sent to the customer.
  sent	Sent	The version has been sent to the customer and is awaiting a response.
  changes_requested	Changes Requested	The customer has requested changes to this sent version.
  accepted	Accepted	The customer has accepted this exact version and its terms.
  declined	Declined	The customer has declined this version.
  expired	Expired	The version validity period ended before the customer accepted it.
  superseded	Superseded	A newer version replaces this version, or the administrator has withdrawn it in favor of a replacement.
6.4 Permitted Quotation Version Status Transitions
Current status	Permitted next status
draft	sent, superseded
sent	changes_requested, accepted, declined, expired, superseded
changes_requested	superseded
accepted	No further status changes
declined	No further status changes
expired	No further status changes
superseded	No further status changes

6.5 Quotation Version Status Transition Rules
- A new quotation version starts as draft.
- Move a version to sent only after the application has successfully submitted it through the selected delivery channel. Record the provider's acceptance/reference where available. A provider accepting a message does not prove that the recipient received or read it.
- A customer response requesting modifications changes the sent version to changes_requested. Create a separate new version in draft for the requested changes. Once the replacement version is ready to be sent, mark the prior version superseded.
- If the customer accepts a version, mark that exact version accepted. Do not edit its contents or terms after acceptance.
- A version can be marked declined only when the customer has declined that version.
- Mark a version expired when its valid_until date/time has passed without acceptance, subject to the application's defined timezone and expiry processing rules.
- Mark a version superseded when it is replaced or withdrawn in favor of another version. Preserve the old version and its quotation items for audit history.
- Only one version of a quotation may be the accepted version. The application must prevent acceptance of a different version after one version has already been accepted, unless a separately approved business process explicitly permits it.
- Acceptance must record the accepted version, acceptance timestamp, and acceptance_method. Populate acceptance_reference only when a protected evidence reference is retained.
- Supported acceptance methods must be controlled by application-level constants, initially secure_link, email, and administrator_recorded. An administrator-recorded acceptance must include the reason and administrator identity in the activity log.
- The customer must be shown the terms of the exact version being accepted.
- Acceptance must be processed atomically. Lock or otherwise serialize acceptance for the quotation so two different versions cannot both be accepted concurrently.
- The quotation master status must reflect the status of the version referenced by current_version_id. When a new version becomes current, update the current-version reference and master status consistently.
- Record every status transition with the previous status, new status, timestamp, actor or system process, and an appropriate reason where applicable.
- Validate all transitions in the application. Do not allow arbitrary status updates that bypass these rules.
- A quotation being accepted does not by itself mean that the booking is confirmed. Booking confirmation follows the separate booking and payment-verification workflow.
7. Quotation Items Table
   Table: {wordpress_prefix}bs_quotation_items
Purpose: Stores individual itinerary components and cost items belonging to a specific quotation version.

Items may represent accommodation, transportation, activities, meals, services, or other components included in a quotation.
Column	Logical data type	Required	Description
id	BIGINT UNSIGNED, auto-increment	Yes	Primary key
quotation_version_id	BIGINT UNSIGNED	Yes	References bs_quotation_versions.id
item_type	VARCHAR(40)	Yes	Category of itinerary or cost item
day_number	BIGINT UNSIGNED	No	Associated itinerary day, where applicable
title	VARCHAR(190)	Yes	Item title
description	TEXT	No	Item description
quantity	DECIMAL(10,2)	Yes	Quantity of the item
unit_amount	DECIMAL(19,4)	Yes	Cost per unit
total_amount	DECIMAL(19,4)	Yes	Total cost for the item
sort_order	BIGINT UNSIGNED	Yes	Display order within the quotation version

7.1 Keys and Indexes
- Primary key: id.
- Foreign key: quotation_version_id references bs_quotation_versions.id.
- Index: quotation_version_id.
- Index: item_type.
- Composite index: (quotation_version_id, sort_order).
  7.2 Data Integrity Rules
- Every quotation item must belong to an existing quotation version.
- Item quantities must be greater than zero.
- Unit amounts and total amounts must not be negative.
- Item totals must be calculated consistently from quantity and unit amount.
- All item amounts must use the currency of their associated quotation version.
- day_number may be empty for items that are not associated with a specific itinerary day.
- Item ordering must be preserved when displaying or generating quotation documents.
- Draft quotation items may be modified before the quotation version is sent.
- Once a quotation version has been sent, its items must not be edited or deleted.
- Changes to sent quotation items must be recorded in a new quotation version.
- Historical quotation items must remain available for accepted quotations and bookings.
- The item_type field must use one of the approved application-level values: accommodation, transport, activity, guide, meal, or other.
- The application must validate item types and reject unsupported values.
8. Booking Table (bs_bookings)
   The bs_bookings table stores reservation records created after a customer accepts a specific quotation version. It tracks the booking lifecycle from pending payment through confirmation, completion or cancellation.
A booking must retain a reference to the exact quotation version accepted by the customer. Accepting a quotation does not automatically confirm the booking.
8.1 Fields
Field	Data Type	Constraints	Description
id	BIGINT UNSIGNED	Primary Key, Auto Increment	Unique booking record identifier.
customer_id	BIGINT UNSIGNED	Not Null	References the associated customer in bs_customers.
quotation_id	BIGINT UNSIGNED	Not Null	References the accepted quotation in bs_quotations.
accepted_version_id	BIGINT UNSIGNED	Not Null	References the exact quotation version accepted by the customer in bs_quotation_versions.
booking_reference	VARCHAR(40)	Not Null, Unique	Unique, non-sequential booking reference.
travel_start_date	DATE	Not Null	Confirmed travel start date.
travel_end_date	DATE	Not Null	Confirmed travel end date.
traveller_count	INT UNSIGNED	Not Null	Confirmed number of travellers.
status	VARCHAR(40)	Not Null	Current booking status.
confirmed_at	DATETIME	Nullable	UTC timestamp when the booking was confirmed.
confirmation_exception_by	BIGINT UNSIGNED	Nullable	WordPress administrator ID authorizing confirmation without the normal verified-deposit condition; logical WordPress reference
confirmation_exception_reason	TEXT	Nullable	Required reason when an administrator confirms the booking by exception
created_at	DATETIME	Not Null	UTC timestamp when the booking record was created.
updated_at	DATETIME	Not Null	UTC timestamp of the latest booking record modification.

8.2 Booking Status Values
The following application-level values are permitted:
Stored Value	Display Label	Description
pending_payment	Pending Payment	The customer has accepted the quotation and the booking exists, but the required deposit has not yet been verified or an authorized exception has not been recorded.
confirmed	Confirmed	The required deposit has been verified, or an authorized administrator has recorded a confirmation exception.
in_progress	In Progress	The customer's trip has started.
completed	Completed	The trip has ended and the booking is operationally complete.
cancelled	Cancelled	The booking has been cancelled and is no longer active.

8.3 Permitted Status Transitions
Current Status	Permitted Next Status
pending_payment	confirmed, cancelled
confirmed	in_progress, cancelled
in_progress	completed, cancelled
completed	No further status changes
cancelled	No further status changes

8.4 Status Transition Rules
- A booking must be created only after the customer accepts a specific quotation version.
- A newly created booking must start with the pending_payment status.
- The booking must reference the exact accepted quotation version through accepted_version_id.
- The booking can move to confirmed only after an administrator verifies the required deposit or records an authorized confirmation exception.
- An authorized confirmation exception must populate confirmation_exception_by, confirmation_exception_reason, and confirmed_at, and must create an activity-log entry.
- The administrator authorizing the exception must have the required capability. The application must not permit an exception without a non-empty reason.
- When confirmation follows the normal verified-deposit condition, the exception fields must remain null.
- A payment record with a pending verification status must not be treated as verified funds.
- The booking must move to in_progress when the trip actually starts.
- The booking must move to completed after the trip has ended and the business has completed its operational responsibilities.
- Cancellation must record the acting administrator, timestamp, reason and applicable cancellation or refund outcome.
- Every status transition must be recorded in the activity log with the previous status, new status, timestamp, actor or system process, and reason where applicable.
- Status transitions must be validated by the application. Arbitrary status updates must not be permitted.
- completed and cancelled are terminal statuses. A new reservation must create a new enquiry and booking rather than reopening an existing booking.
  8.5 Data Integrity Rules
- customer_id must reference an existing customer.
- quotation_id must reference an existing quotation.
- accepted_version_id must reference an existing version belonging to the quotation identified by quotation_id.
- The referenced quotation version must have an accepted status when the booking is created.
- The quotation's customer and the booking's customer must match.
- booking_reference must be unique and must not expose personal information or sequential database identifiers.
- travel_end_date must not be earlier than travel_start_date.
- traveller_count must be greater than zero.
- confirmed_at must be set when the booking transitions to confirmed and must retain the original confirmation timestamp.
- confirmation_exception_by and confirmation_exception_reason must be populated together only when confirmation occurs without the normal verified-deposit condition. Validate the administrator against WordPress users.
- All timestamps must be stored in UTC and displayed using the configured Bhutan timezone.
- Booking records must not be automatically deleted when a customer or quotation is deleted.
- Historical booking and payment records must be preserved for operational and financial traceability.
  8.6 Booking Reference Format
  Use the following reference format:
B-YYYY-RANDOM

Where:
- B identifies the record as a booking.
- YYYY represents the booking creation year.
- RANDOM is a cryptographically generated, non-sequential string containing at least 12 uppercase alphanumeric characters.
  The booking reference must have a unique database constraint. It is an identification reference only and must not grant access to booking information.
  8.7 Relationships
  Related Table	Relationship	Description
  bs_customers	Many bookings to one customer	A customer can have multiple bookings.
  bs_quotations	Many bookings to one quotation	A booking references the quotation from which it was created.
  bs_quotation_versions	Many bookings to one accepted version	Each booking retains the exact quotation version accepted by the customer.
  bs_payments	One booking to many payment records	A booking can have multiple payment and refund records.
  bs_activity_log	Related activity records	Booking creation, status changes and administrative actions are recorded for auditing.
8.8 Deletion and Audit Rules
- Booking records must not be cascade-deleted when related customer or quotation records are deleted.
- Bookings associated with financial records must be preserved.
- Cancellation must update the booking status and retain the historical record.
- Payment and refund history must remain available for reconciliation.
- Every booking status change and administrative exception must be recorded in bs_activity_log.
- If a WordPress administrator account is deleted, the historical actor information must remain identifiable through the activity log.
9. Payment Table (bs_payments)
   The bs_payments table stores manually recorded payment receipts and refunds associated with bookings. It maintains payment verification records and supports booking balance calculations.
Payments are made outside the application through external methods such as bank transfer or cash. The website does not collect or process payment credentials and does not integrate a payment gateway.

The customer pays through the agreed external channel. The customer may provide payment evidence through WhatsApp, email, or a secure website submission link. An administrator independently checks the bank transaction or confirms receipt of cash, then manually creates or updates the payment record in the dashboard. Submitting evidence does not automatically verify a payment. Only an authorized administrator can change a payment from pending_verification to verified or rejected.
9.1 Fields
Field	Data Type	Constraints	Description
id	BIGINT UNSIGNED	Primary Key, Auto Increment	Unique payment record identifier.
booking_id	BIGINT UNSIGNED	Not Null	References the associated booking in bs_bookings.
payment_type	VARCHAR(20)	Not Null	Identifies the record as a receipt or refund.
related_payment_id	BIGINT UNSIGNED	Nullable	References the original verified receipt when the record represents a refund.
amount	DECIMAL(19,4)	Not Null	Amount received or refunded, stored as a positive value.
currency	CHAR(3)	Not Null	Currency actually received or refunded, represented by its currency code.
quotation_currency_equivalent	DECIMAL(19,4)	Nullable	Amount expressed in the booking's quotation currency for balance calculations, where conversion is required.
exchange_rate	DECIMAL(20,10)	Nullable	Exchange rate applied when converting the payment currency to the quotation currency.
exchange_rate_source	VARCHAR(255)	Nullable	Source of the applied exchange rate.
exchange_rate_date	DATE	Nullable	Date the applied exchange rate was obtained or fixed.
payment_method	VARCHAR(40)	Not Null	External payment method, such as bank transfer or cash.
payment_date	DATE	Not Null	Date the payment was received or refunded.
reference	VARCHAR(255)	Nullable	Optional external transaction reference.
verification_status	VARCHAR(40)	Not Null	Current payment verification status.
verified_by	BIGINT UNSIGNED	Nullable	WordPress administrator ID of the administrator who verified or rejected the record.
verified_at	DATETIME	Nullable	UTC timestamp when the record was verified or rejected.
verification_reason	TEXT	Nullable	Required reason when a payment is rejected; may also record a concise verification note without storing sensitive banking data.
evidence_reference	VARCHAR(500)	Nullable	Protected reference to submitted payment evidence, if uploaded through the website; never a public URL or physical server path.
created_at	DATETIME	Not Null	UTC timestamp when the payment record was created.

9.2 Payment Types
The following application-level values are permitted:
Stored Value	Description
receipt	Records money received from a customer.
refund	Records money refunded to a customer and must reference the original verified receipt.

The amount field must always contain a positive value. The payment_type determines whether the record represents received or refunded funds.

Approved initial payment_method values are bank_transfer and cash. Additional external methods may be introduced only through an explicitly approved application-level value. Do not add card, wallet, or payment-gateway methods unless the business later approves a separate payment-processing design.
9.3 Payment Verification Status Values
The following application-level values are permitted:
Stored Value	Display Label	Description
pending_verification	Pending Verification	The payment or refund has been recorded but has not yet been checked by an administrator.
verified	Verified	An administrator has checked the external transaction and confirmed the receipt or refund.
rejected	Rejected	The submitted payment or refund record could not be verified and must not affect the booking balance.

9.4 Permitted Verification Status Transitions
Current Status	Permitted Next Status
pending_verification	verified, rejected
verified	No further status changes
rejected	No further status changes

9.5 Payment Verification Rules
- Every new receipt or refund record must start with pending_verification.
- Only an authorized administrator may verify or reject a payment record.
- A verified payment record is immutable.
- Verified and rejected records are terminal and must not be reopened or silently overwritten.
- If a record contains an administrative data-entry error, preserve the original record, record the correction and reason in bs_activity_log, and create a replacement record only when it represents a genuine receipt or refund that can be independently substantiated. Do not create a second financial receipt merely to adjust descriptive metadata.
- A rejected record must remain in the audit history.
- If a customer provides corrected payment evidence after rejection, create a new payment record and link the correction context in the activity log. Do not reopen or overwrite the rejected record.
- Record the administrator responsible for verification or rejection and the verification timestamp.
- A rejection must include a reason in verification_reason and a corresponding activity-log entry.
- Only verified receipts count as funds received.
- Only verified refunds reduce the net amount received.
- A payment record with pending_verification or rejected status must not affect the booking balance.
- All verification status transitions must be validated by the application.
  9.6 Refund Rules
- A refund must be recorded as a separate payment record with payment_type set to refund.
- Every refund must reference its original verified receipt through related_payment_id.
- The original receipt must not be modified or deleted to represent a refund.
- The application must prevent verified refunds from exceeding the remaining refundable amount of the original receipt.
- Multiple partial refunds may reference the same original receipt, provided their combined verified amount does not exceed the remaining refundable amount.
- Refund verification must follow the same verification rules as payment receipts.
- Refund records must remain available for financial reconciliation and auditing.
  9.7 Currency and Balance Calculation Rules
- Store the actual amount and currency received or refunded.
- When conversion is required, store the quotation-currency equivalent, exchange rate, exchange-rate source, and exchange-rate date.
- The payment exchange-rate direction is quotation-currency units per one unit of actual payment currency.
- The quotation-currency equivalent for a refund must use the applicable conversion information for that refund, not a later recalculation of the original receipt.
- Use the currency conversion and rounding rules defined in the approved database architecture. The final rounding mode and currency-specific decimal handling must be explicitly confirmed before implementation; this schema does not invent those policy values.
- Do not use floating-point arithmetic for payment or balance calculations.
- Booking payment totals must include verified receipts less verified refunds, using quotation_currency_equivalent when the received/refunded currency differs from the quotation currency.
- Pending and rejected payment records must be excluded from booking balance calculations.
- Historical payment conversion values must be preserved and must not be silently recalculated when exchange rates change.
  9.8 Data Integrity Rules
- booking_id must reference an existing booking in bs_bookings.
- related_payment_id is required for a refund and must reference an existing verified receipt.
- The referenced receipt must belong to the same booking as the refund.
- related_payment_id must be null for a receipt.
- A refund must reference a receipt belonging to the same booking.
- A refund must not exceed the remaining refundable amount of its original receipt.
- amount must be greater than zero.
- payment_type must contain only receipt or refund.
- payment_method must contain an approved external method, initially bank_transfer or cash.
- verification_status must contain only the approved verification status values.
- verified_by and verified_at must be populated when a record is verified or rejected.
- Exchange-rate fields must be populated when currency conversion is required.
- For payment conversion, exchange_rate means quotation-currency units per one unit of the actual payment currency. For example, if the payment is in USD and the quotation is in BTN, the rate expresses BTN per USD.
- When the payment currency equals the quotation currency, the application may store an equivalent amount equal to amount and a rate of 1, or leave conversion fields null according to one consistent implementation rule. The chosen rule must be applied consistently.
- quotation_currency_equivalent must be the amount used for booking balance calculations when currencies differ. Never add raw amounts from different currencies together.
- Payment and refund records must not be cascade-deleted when a booking is deleted.
- All timestamps must be stored in UTC and displayed using the configured Bhutan timezone.
  9.9 Relationships
  Related Table	Relationship	Description
  bs_bookings	Many payment records to one booking	A booking can have multiple payment receipts and refunds.
  bs_payments	One receipt to many refund records	A verified receipt can have multiple partial refund records.
  bs_activity_log	Related activity records	Payment creation, verification, rejection, and correction actions are recorded for auditing.
9.10 Deletion and Audit Rules
- Payment and refund records must be preserved for financial traceability.
- Verified payment records must never be silently overwritten or deleted.
- Rejected records must remain in the audit history.
- Corrections must preserve the original record and create an auditable correction.
- Payment creation, verification, rejection, correction, and refund actions must be recorded in bs_activity_log, including actor, timestamp, and reason where applicable.
- Deleting a WordPress administrator account must not erase historical payment verification information.
- Sensitive banking credentials, card numbers, passwords, and access tokens must never be stored in payment records or activity logs.
10. Documents Table (bs_documents)
    The bs_documents table stores references to generated business documents associated with customers, quotations, bookings, and payments.
The table manages document metadata and protected file references. It does not store document contents directly in the database.
10.1 Fields
Field	Data Type	Constraints	Description
id	BIGINT UNSIGNED	Primary Key, Auto Increment	Unique document record identifier.
customer_id	BIGINT UNSIGNED	Not Null	References the associated customer in bs_customers.
quotation_id	BIGINT UNSIGNED	Nullable	References the associated quotation in bs_quotations, where applicable.
booking_id	BIGINT UNSIGNED	Nullable	References the associated booking in bs_bookings, where applicable.
payment_id	BIGINT UNSIGNED	Nullable	References the associated payment in bs_payments, where applicable.
document_type	VARCHAR(40)	Not Null	Identifies the document type, such as quotation, invoice, booking confirmation, receipt, or other.
file_reference	VARCHAR(500)	Not Null	Protected reference to the stored document. Must not contain a publicly accessible URL or expose a physical server path.
created_at	DATETIME	Not Null	UTC timestamp when the document record was created.

10.2 Document Types
The following application-level document types are supported:
Document Type	Description
quotation	Generated quotation document sent to a customer.
invoice	Invoice associated with a booking.
booking_confirmation	Document confirming a booking.
receipt	Payment receipt issued for a recorded payment.
other	Other approved business documents.

10.3 Document Association Rules
- A document must be associated with an existing customer.
- A document may be associated with a quotation, a booking, a payment, or multiple related records, depending on its purpose.
- A receipt document should reference its associated payment through payment_id.
- The application must validate that the referenced quotation, booking, and payment belong to the associated customer.
- When a payment is associated with a document, it must belong to the same booking and customer as the document, where applicable.
- payment_id must remain null for documents that are not associated with a payment.
- A document associated with a booking must not be deleted when the booking is cancelled.
- Historical documents must remain available according to the applicable data-retention policy.
- The application must validate document associations before creating or updating a document record.
  10.4 Document Storage and Access Rules
- Store private documents outside the publicly accessible WordPress uploads directory, or use an equivalent protected storage mechanism.
- Store only a protected file reference in file_reference.
- Do not expose physical server paths or predictable file locations.
- Serve documents only through authenticated or securely authorized download endpoints.
- Verify the administrator's permissions or the customer's access token before serving a document.
- Guest access must use secure, random, expiring, and revocable access tokens.
- Expired or revoked access links must no longer provide access to documents.
- Publicly accessible upload URLs must not be used for private business documents.
- The final storage mechanism and operational backup procedure must be selected before implementation.
  10.5 Data Integrity Rules
- customer_id must reference an existing customer.
- quotation_id, when supplied, must reference an existing quotation.
- booking_id, when supplied, must reference an existing booking.
- payment_id, when supplied, must reference an existing payment.
- The associated quotation, booking, and payment must belong to the specified customer.
- When both booking_id and payment_id are supplied, the payment must belong to that booking.
- document_type must contain only an approved document type.
- file_reference must identify a valid protected document resource.
- Document records must not be cascade-deleted when related customer, quotation, booking, or payment records are deleted.
- All timestamps must be stored in UTC and displayed using the configured Bhutan timezone.
  10.6 Relationships
  Related Table	Relationship	Description
  bs_customers	Many documents to one customer	A customer can have multiple generated documents.
  bs_quotations	Many documents to one quotation	A quotation can have associated documents, such as generated quotation PDFs.
  bs_bookings	Many documents to one booking	A booking can have associated documents, such as invoices, confirmations, and receipts.
  bs_payments	Many documents to one payment	A payment can have associated receipt documents.
  bs_access_tokens	Related access records	Guest access to a document can be controlled through scoped access tokens.
  bs_activity_log	Related activity records	Document creation and administrative access actions can be recorded for auditing.
10.7 Deletion and Audit Rules
- Private business documents must not be permanently deleted while they are required for operational or financial records.
- Deletion or archival must follow the approved data-retention policy.
- Deleting a customer, quotation, or booking must not automatically delete associated document files.
- Access tokens associated with a document must be revoked when access is no longer required or the document is withdrawn.
- Document creation, deletion, and relevant administrative actions must be recorded in bs_activity_log.
- Physical document deletion must be restricted to authorized administrative operations.
11. Customer Access Tokens Table (bs_access_tokens)
    The bs_access_tokens table stores token hashes and access-control metadata for secure guest access to quotations, bookings, and documents.
Customers can access authorized resources through secure links without creating website accounts.
11.1 Fields
Field	Data Type	Constraints	Description
id	BIGINT UNSIGNED	Primary Key, Auto Increment	Unique access token record identifier.
token_hash	VARCHAR(255)	Not Null, Unique	Hash of the generated access token. The original usable token must never be stored.
customer_id	BIGINT UNSIGNED	Not Null	References the customer to whom access is granted in bs_customers.
entity_type	VARCHAR(40)	Not Null	Identifies the resource type, such as quotation, booking, or document.
entity_id	BIGINT UNSIGNED	Not Null	Identifier of the specific resource to which access is granted.
expires_at	DATETIME	Not Null	UTC timestamp when the access token expires.
revoked_at	DATETIME	Nullable	UTC timestamp when the token was revoked. Remains null while the token is active.
last_used_at	DATETIME	Nullable	UTC timestamp of the most recent successful token use, where tracked.
created_at	DATETIME	Not Null	UTC timestamp when the token was created.
created_by	BIGINT UNSIGNED	Nullable	WordPress administrator ID of the administrator who generated the token, where applicable.

11.2 Supported Resource Types
The following application-level resource types are supported:
Stored Value	Description
quotation	Grants access to a specific quotation.
booking	Grants access to a specific booking.
document	Grants access to a specific document.

11.3 Token Generation and Storage Rules
- Generate tokens using a cryptographically secure random generator.
- Store only the hash of each generated token in token_hash.
- Never store the original usable token in the database.
- Never expose token hashes through public responses or application interfaces.
- Enforce uniqueness for token hashes.
- Generate a new token when access must be renewed.
- Do not reuse expired or revoked tokens.
  11.4 Access Validation Rules
- Validate the presented token securely before granting access.
- Verify that the token has not expired.
- Verify that the token has not been revoked.
- Verify that the requested resource matches entity_type and entity_id.
- Verify that the resource belongs to the customer identified by customer_id.
- A token must grant access only to its intended resource.
- Tokens must not provide general customer-account access or access to unrelated records.
- Reject invalid, expired, revoked, or incorrectly scoped tokens.
- Update last_used_at after successful access, where tracking is enabled.
  11.5 Token Expiry and Revocation Rules
- Every token must have an expiry timestamp.
- Administrators must be able to revoke tokens before their expiry.
- Expired and revoked tokens must no longer grant access.
- Revoke relevant tokens when access is no longer required or when the associated resource is withdrawn.
- Generate a new token when access needs to be restored.
- Token expiry and revocation must be enforced by the application, not merely hidden from the user interface.
  11.6 Data Integrity Rules
- customer_id must reference an existing customer.
- entity_type must contain only an approved resource type.
- entity_id must identify an existing resource of the specified type.
- The referenced resource must belong to the customer identified by customer_id.
- token_hash must be unique and not null.
- expires_at must be later than created_at.
- revoked_at, when populated, must represent a valid revocation timestamp.
- Polymorphic resource references must be validated by the application because a conventional foreign key cannot directly reference multiple tables.
  11.7 Relationships
  Related Table	Relationship	Description
  bs_customers	Many tokens to one customer	A customer can have multiple access tokens.
  bs_quotations	Polymorphic reference	A token can grant access to a specific quotation.
  bs_bookings	Polymorphic reference	A token can grant access to a specific booking.
  bs_documents	Polymorphic reference	A token can grant access to a specific document.
  bs_activity_log	Related activity records	Token creation, revocation, and relevant administrative actions can be recorded for auditing.
11.8 Deletion and Audit Rules
- Token records must be retained or archived according to the applicable security and data-retention policy.
- Revocation must not erase the token's historical record.
- Tokens must be revoked when their associated access is no longer required.
- Token creation and revocation must be recorded in bs_activity_log.
- Raw tokens, token hashes, and other authentication secrets must never be written to activity logs.
- Deleting a WordPress administrator account must not erase historical token-management activity.
12. Activity Log Table (bs_activity_log)
    The bs_activity_log table records important administrative and business-related actions performed within the Blue Sheep Business Manager.
It provides an audit trail for monitoring changes to customers, enquiries, quotations, bookings, payments, documents, and access permissions.
12.1 Fields
<!-- BSBM-ACTIVITY-LOG-FIELDS -->
Additional fields:
- previous_status VARCHAR(40) NULL: Stores the status before a status transition.
- new_status VARCHAR(40) NULL: Stores the status after a status transition.
- reason TEXT NULL: Stores the explanation for an action or status transition.
  Field	Data Type	Constraints	Description
  id	BIGINT UNSIGNED	Primary Key, Auto Increment	Unique activity log identifier.
user_id	BIGINT UNSIGNED	Nullable	WordPress administrator ID of the user who performed the action, where applicable.
entity_type	VARCHAR(40)	Not Null	Type of affected record, such as customer, enquiry, quotation, booking, payment, document, or access token.
entity_id	BIGINT UNSIGNED	Not Null	Identifier of the affected record.
action	VARCHAR(80)	Not Null	Action performed, such as created, updated, status_changed, verified, rejected, or revoked.
description	TEXT	Nullable	Brief description of the activity.
created_at	DATETIME	Not Null	UTC timestamp when the activity occurred.

12.2 Logged Activities
The application should record significant actions, including:
- Customer record creation and updates.
- Enquiry creation and status changes.
- Quotation creation, revision, sending, acceptance, and rejection.
- Booking creation and status changes.
- Payment submission, verification, and rejection.
- Document creation and relevant administrative actions.
- Access token creation and revocation.
- Important administrative changes to business records.
  12.3 Activity Recording Rules
<!-- BSBM-ACTIVITY-LOG-RULES -->Additional recording rules:
- For status changes, record the previous and new status separately.
- Record the reason for the transition when applicable.
- Use status_changed as the action identifier for status transitions.
- Keep previous_status and new_status NULL for activities that do not involve a status change.
- Record the responsible administrator where applicable.
- System-generated activities should be identifiable in the activity description.
- Record the WordPress administrator responsible for the action, where applicable.
- Record the affected entity type and entity ID.
- Use consistent action identifiers.
- Store timestamps in UTC.
- Descriptions should be brief and understandable.
- Record important status transitions so the history of a business record can be reconstructed.
- Activity records should be created by the application when the corresponding action succeeds.
  12.4 Sensitive Information Rules
  The activity log must never contain:
- Passwords or authentication credentials.
- Raw access tokens or token hashes.
- Payment card or banking credentials.
- SMTP credentials or API secrets.
- Unnecessary personal information.
- Full private document contents.
- Other sensitive information that is not required for auditing.
  12.5 Data Integrity Rules
<!-- BSBM-ACTIVITY-LOG-INTEGRITY -->Additional integrity rules:
- When action is status_changed, both previous_status and new_status must be populated.
- For other actions, these fields may remain NULL.
- Activity records must preserve historical status transitions.
- Activity records must not be silently modified or deleted during normal operations.
- entity_type and entity_id must identify the affected business record.
- user_id, when populated, must identify the relevant WordPress administrator.
- Polymorphic entity references must be validated by the application because a conventional foreign key cannot directly reference multiple tables.
- Activity records must not be silently modified or deleted during normal business operations.
  12.6 Relationships
  Related Table	Relationship	Description
  WordPress Users	Logical reference	Identifies the administrator who performed the action, where applicable.
  Business Tables	Polymorphic reference	Identifies the affected customer, enquiry, quotation, quotation version, quotation item, booking, payment, document, or access token.
12.7 Retention and Deletion Rules
- Activity records must be preserved according to the applicable data-retention policy.
- Deleting a business record must not automatically erase its activity history.
- Deleting a WordPress administrator account must not erase historical activity.
- Activity records should be read-only during normal business operations.
- Any exceptional administrative correction must itself be auditable.
13. Notification Log Table (bs_notification_log)
    The bs_notification_log table records the delivery status and attempt summary of notifications generated by the Blue Sheep Business Manager.
It supports monitoring automated customer communications and administrative notifications.
13.1 Fields
Field	Data Type	Constraints	Description
id	BIGINT UNSIGNED	Primary Key, Auto Increment	Unique notification log identifier.
event_type	VARCHAR(80)	Not Null	Type of notification event, such as enquiry acknowledgement, quotation delivery, or booking update.
related_entity_type	VARCHAR(40)	Nullable	Type of related business record, where applicable.
related_entity_id	BIGINT UNSIGNED	Nullable	Identifier of the related business record, where applicable.
recipient_reference	VARCHAR(255)	Nullable	Recipient email address or protected recipient reference, subject to data minimization.
delivery_status	VARCHAR(30)	Not Null	Current delivery status of the notification.
provider_reference	VARCHAR(255)	Nullable	Optional message identifier returned by the email provider.
attempt_count	INT UNSIGNED	Not Null, Default 0	Number of delivery attempts.
last_attempt_at	DATETIME	Nullable	UTC timestamp of the most recent delivery attempt.
created_at	DATETIME	Not Null	UTC timestamp when the notification log record was created.

13.2 Delivery Status Values
The application supports the following delivery statuses:
Status	Description
queued	Notification is waiting to be sent.
sent	Notification was successfully submitted to the delivery provider.
failed	Notification delivery attempt failed.
retrying	Notification is scheduled for another delivery attempt.

13.3 Notification Events
The table may track events such as:
- Enquiry acknowledgement.
- Quotation delivery.
- Quotation acceptance or modification updates.
- Payment verification updates.
- Booking confirmation.
- Booking status updates.
- Pre-departure reminders.
- Outstanding balance reminders.
- Cancellation notifications.
- Administrative notifications.
  13.4 Delivery and Retry Rules
- Create a notification log record when a notification is queued.
- Increment attempt_count for each delivery attempt.
- Update last_attempt_at whenever a delivery attempt occurs.
- Treat attempt_count, last_attempt_at, delivery_status, and provider_reference as a summary of the latest delivery state, not a complete per-attempt event history.
- Record significant retry and failure events in bs_activity_log where operationally useful, without storing message bodies or secrets.
- If a complete immutable history of every delivery attempt becomes a requirement, approve a separate bs_notification_attempts table as an explicit architecture change. Do not silently add a twelfth table to this eleven-table schema.
- Record the provider's message identifier when available.
- Update delivery_status according to the delivery process.
- Failed notifications may be retried according to the configured retry policy.
- Avoid duplicate notifications when retrying.
- Notification delivery must not independently change quotation, payment, or booking statuses.
- A notification being marked as sent means it was accepted by the delivery provider, not necessarily read by the recipient.
  13.5 Sensitive Information Rules
  The notification log must not contain:
- SMTP passwords or credentials.
- API keys or provider secrets.
- Access tokens or token hashes.
- Full notification message bodies.
- Unnecessary personal information.
- Payment credentials or other sensitive customer information.
  Use approved notification templates and retrieve business details from their associated records when generating messages.
  13.6 Data Integrity Rules
- event_type must identify a supported notification event.
- delivery_status must contain an approved application-level status.
- attempt_count must be zero or greater.
- last_attempt_at must remain null until a delivery attempt occurs.
- sent means the provider accepted the message submission. It does not prove delivery to, or reading by, the recipient.
- Related entity references must be validated by the application.
- Provider references must be stored only when returned by the delivery provider.
  13.7 Relationships
  Related Table	Relationship	Description
  Business Tables	Polymorphic reference	Associates a notification with a customer, enquiry, quotation, booking, payment, or other relevant business record.
  Notification Templates	Application-level reference	Uses approved templates to generate notification content.
  Email Delivery Provider	External integration	Handles notification delivery and may return a provider message identifier.
13.8 Retention and Deletion Rules
- Retain notification logs according to the applicable data-retention policy.
- Preserve failed delivery records for troubleshooting and audit purposes.
- Do not delete business records as a side effect of notification-log cleanup.
- Do not store full message bodies or credentials in notification logs.
14. Database Relationships
    This section defines how the Blue Sheep Business Manager's custom database tables relate to one another.
The relationships support customer enquiries, quotation preparation, booking management, payment tracking, document access, and administrative auditing.
14.1 Relationship Overview
Parent Table	Related Table	Relationship	Description
bs_customers	bs_enquiries	One-to-Many	A customer can submit multiple enquiries. Each enquiry belongs to one customer.
bs_customers	bs_quotations	One-to-Many	A customer can have multiple quotations. Each quotation belongs to one customer.
bs_customers	bs_bookings	One-to-Many	A customer can have multiple bookings. Each booking belongs to one customer.
bs_customers	bs_documents	One-to-Many	A customer can have multiple associated documents.
bs_customers	bs_access_tokens	One-to-Many	A customer can have multiple access tokens.
bs_enquiries	bs_quotations	One-to-Many	An enquiry may result in multiple quotations or quotation records, where required.
bs_quotations	bs_quotation_versions	One-to-Many	A quotation can contain multiple versions.
bs_quotations.current_version_id	bs_quotation_versions.id	Many-to-One pointer	Identifies the quotation's currently selected version. The referenced version must belong to the same quotation.
bs_quotation_versions	bs_quotation_items	One-to-Many	Each quotation version can contain multiple itinerary and pricing items.
bs_quotations	bs_bookings	One-to-Many	A quotation may be associated with booking records.
bs_quotation_versions	bs_bookings	One-to-Many	A booking references the exact quotation version accepted by the customer.
bs_bookings	bs_payments	One-to-Many	A booking can have multiple receipt and refund records.
bs_payments	bs_payments	Self-referencing	A refund can reference the original verified receipt.
bs_quotations	bs_documents	One-to-Many	A quotation can have associated documents.
bs_bookings	bs_documents	One-to-Many	A booking can have associated documents.
bs_payments	bs_documents	One-to-Many	A payment can have associated receipt documents.
bs_customers	bs_activity_log	Polymorphic reference	Activity records may identify actions affecting customer records.
Business Tables	bs_activity_log	Polymorphic reference	Activity records identify affected business records through entity type and entity ID.
bs_customers	bs_notification_log	Polymorphic reference	Notifications may relate to customer records where applicable.
Business Tables	bs_notification_log	Polymorphic reference	Notifications may reference enquiries, quotations, bookings, payments, or other relevant records.
Business Tables	bs_access_tokens	Polymorphic reference	An access token grants access to one specific quotation, booking, or document.

14.2 Relationship Rules
- Every enquiry must belong to an existing customer.
- Every quotation must belong to an existing customer.
- A quotation created from an enquiry must reference the relevant enquiry.
- Every quotation version must belong to an existing quotation.
- Every quotation's current_version_id, when populated, must identify a version whose quotation_id equals the quotation's id.
- Every quotation item must belong to a specific quotation version.
- Every booking must belong to an existing customer and reference its quotation and accepted quotation version.
- The accepted quotation version must belong to the quotation referenced by the booking.
- Every payment must reference an existing booking.
- A refund must reference its original verified receipt, and both records must belong to the same booking.
- Every document must reference an existing customer. Optional quotation, booking, and payment references must identify valid records belonging to that customer.
- When a document references both a payment and a booking, the payment must belong to that booking.
- Every access token must reference an existing customer and a valid resource of the specified type. The resource must belong to that same customer.
- Activity and notification records must reference valid business entities where a related entity is specified.
  14.3 Foreign Key and Application-Level Relationships
  Relationships must be implemented according to their reference type.
Direct relationships

Where both records are stored in custom database tables, use foreign keys where supported and compatible with the target database environment.

Examples include:
- bs_enquiries.customer_id → bs_customers.id
- bs_quotations.customer_id → bs_customers.id
- bs_quotation_versions.quotation_id → bs_quotations.id
- bs_quotations.current_version_id → bs_quotation_versions.id (validate that the version belongs to the same quotation; create the quotation first, then its first version, then set this pointer)
- bs_quotation_items.quotation_version_id → bs_quotation_versions.id
- bs_bookings.customer_id → bs_customers.id
- bs_payments.booking_id → bs_bookings.id
- bs_documents.payment_id → bs_payments.id
  Polymorphic relationships
Some tables reference different resource types through an entity type and entity ID combination.

Examples include:
- bs_access_tokens.entity_type and bs_access_tokens.entity_id
- bs_activity_log.entity_type and bs_activity_log.entity_id
- bs_notification_log.related_entity_type and bs_notification_log.related_entity_id
  These relationships must be validated by the application because a conventional foreign key cannot directly reference multiple possible tables.
WordPress relationships

References to WordPress users and public content, such as tour posts, must be validated against the relevant WordPress records. They should not be treated as conventional foreign keys unless the implementation and migration strategy explicitly support them.
14.4 Quotation and Booking Integrity
- Each quotation version must remain associated with its parent quotation.
- The quotation's current version reference must point to a version belonging to that quotation.
- A booking must preserve the exact quotation version accepted by the customer.
- Changes to a quotation after acceptance must create a new version rather than modify the accepted version.
- A booking's accepted version must remain unchanged after confirmation.
- The quotation, version, and booking relationships must be validated together when a booking is created.
  14.5 Deletion and Referential Integrity
- Do not cascade-delete bookings, payment records, accepted quotation versions, or financial history.
- Restrict deletion of records referenced by active business transactions.
- Preserve historical records required for audit and reconciliation.
- Use soft deletion or anonymization where appropriate and legally permitted.
- Revoke access tokens when their associated resource is withdrawn or access is no longer required.
- Deletion of a WordPress administrator must not erase business activity history.
  14.6 Relationship Implementation Notes
- Use consistent BIGINT UNSIGNED identifiers for custom table primary and foreign key fields.
- Add indexes to foreign key columns and frequently queried relationship fields.
- Ensure related records belong to the same customer before accepting a relationship.
- Use transactions for multi-record business operations where supported. Acceptance of a quotation version, creation of its booking, and updates to current-version pointers must be protected against partial or concurrent conflicting operations.
- Validate relationships in application logic even when database constraints are present.
- Confirm compatibility with the production database engine before implementing foreign keys or other database-specific features.
15. Database Indexes and Constraints
    This section defines the indexing and constraint requirements for the Blue Sheep Business Manager's custom database tables.
Indexes support efficient record retrieval, while constraints help maintain data integrity and prevent invalid relationships.
15.1 Primary Key Requirements
- Every custom table must have a primary key named id.
- Use BIGINT UNSIGNED with auto-increment for custom table primary keys.
- Each primary key must uniquely identify its record.
- Primary keys must not be reused after record deletion.
  15.2 Unique Constraints
  The following fields require uniqueness:
  Table	Field or combination	Purpose
  bs_access_tokens	token_hash	Prevents duplicate stored token hashes.
  bs_quotations	reference_number	Ensures each customer-facing quotation reference is unique.
  bs_bookings	booking_reference	Ensures each booking reference is unique.
  bs_quotation_versions	(quotation_id, version_number)	Prevents duplicate version numbers within one quotation.
  bs_quotations	reference_number	Ensures quotation references are unique.
  bs_bookings	booking_reference	Ensures booking references are unique.
  bs_quotation_versions	(quotation_id, version_number)	Ensures version numbers are unique within a quotation.
  bs_customers	None by default	Customer email addresses must not be globally unique because multiple records may share an email address.
A unique constraint already creates an index for its key in the supported database engines. Do not add a second ordinary index over the same token_hash, reference_number, or booking_reference columns.

Business reference numbers must be generated using a sufficiently random, non-sequential format and must be unique within their relevant table.
15.3 Foreign Key Requirements
Where supported by the database engine and compatible with the production environment:
- Use foreign keys for direct relationships between custom tables.
- Ensure referencing and referenced columns use compatible data types.
- Use indexes on foreign key columns.
- Apply restrictive deletion behavior to financial and historical records.
- Do not use cascading deletion for bookings, payments, accepted quotation versions, or financial history.
  Polymorphic references and WordPress record references must be validated by the application.
  15.4 Recommended Indexes
  The following indexes should be considered during implementation:
  Table	Field or Field Combination	Purpose
  bs_customers	email	Support customer lookup without enforcing uniqueness.
  bs_enquiries	customer_id	Retrieve enquiries belonging to a customer.
  bs_enquiries	status	Filter enquiries by status.
  bs_enquiries	created_at	Retrieve enquiries by creation date.
  bs_quotations	customer_id	Retrieve quotations belonging to a customer.
  bs_quotations	enquiry_id	Find quotations originating from an enquiry.
  bs_quotations	status	Filter quotations by status.
  bs_quotations	current_version_id	Retrieve and validate the selected current version.
  bs_quotation_versions	quotation_id	Retrieve versions belonging to a quotation.
  bs_quotation_versions	status	Filter quotation versions by status.
  bs_quotation_items	quotation_version_id	Retrieve items belonging to a quotation version.
  bs_bookings	customer_id	Retrieve customer bookings.
  bs_bookings	quotation_id	Retrieve bookings associated with a quotation.
  bs_bookings	accepted_version_id	Retrieve the accepted quotation version.
  bs_bookings	status	Filter bookings by status.
  bs_payments	booking_id	Retrieve payments belonging to a booking.
  bs_payments	related_payment_id	Retrieve refunds associated with original receipts.
  bs_payments	verification_status	Filter payments by verification status.
  bs_documents	customer_id	Retrieve customer documents.
  bs_documents	quotation_id	Retrieve quotation documents.
  bs_documents	booking_id	Retrieve booking documents.
  bs_documents	payment_id	Retrieve documents associated with a specific payment.
  bs_access_tokens	token_hash (unique)	Enforce uniqueness and locate a token hash efficiently.
  bs_access_tokens	customer_id	Retrieve tokens belonging to a customer.
  bs_access_tokens	entity_type, entity_id	Retrieve tokens associated with a specific resource.
  bs_access_tokens	expires_at, revoked_at	Support token expiry and revocation checks.
  bs_activity_log	entity_type, entity_id	Retrieve activity for a specific business record.
  bs_activity_log	user_id	Retrieve activity performed by an administrator.
  bs_activity_log	created_at	Retrieve activity by date.
  bs_notification_log	delivery_status	Filter notifications by delivery status.
  bs_notification_log	event_type	Retrieve notifications by event type.
  bs_notification_log	related_entity_type, related_entity_id	Retrieve notifications associated with a business record.
  bs_notification_log	created_at	Retrieve notification history by date.
15.5 Composite Index Considerations
Composite indexes should be introduced where common queries filter or sort by multiple fields.

Potential combinations include:
- bs_enquiries: status, created_at.
- bs_quotations: customer_id, status.
- bs_quotation_versions: quotation_id, created_at.
- bs_quotation_items: quotation_version_id, sort_order (supports ordered item retrieval; retain the corresponding composite index).
- bs_bookings: status, travel_start_date.
- bs_payments: booking_id, verification_status.
- bs_activity_log: entity_type, entity_id, created_at.
- bs_notification_log: delivery_status, created_at.
  The final index definitions should be based on actual application queries and query performance measurements.
  15.6 Constraint and Validation Rules
- Required fields must not accept null values.
- Optional fields may be null where defined.
- Status values must be validated against approved application-level constants.
- Dates must be validated before insertion or update.
- Monetary amounts must use the approved decimal precision.
- Exchange rates must use the approved precision and direction.
- Foreign key relationships must reference valid records.
- Customer ownership must be verified across related records.
- Token scope, expiry, and revocation must be validated before access is granted.
- Application validation must remain in place even when database constraints are available.
  15.7 Index Maintenance and Performance
- Avoid creating duplicate or unnecessary indexes.
- Review indexes when application query patterns change.
- Use query analysis to identify slow database operations.
- Monitor database performance as business records increase.
- Test index changes before applying them to production.
- Include index definitions in version-controlled database migrations.
16. Database Migration and Versioning
    This section defines the requirements for introducing, updating, and maintaining the Blue Sheep Business Manager database schema.
Database changes must be version-controlled, tested, and applied through controlled migrations.
16.1 Migration Principles
- Every database schema change must be introduced through a versioned migration.
- Migrations must be maintained within the custom Blue Sheep Business Manager plugin.
- Each migration must have a unique version identifier.
- Migration files must be maintained in Git.
- Migrations must be tested in the local environment before staging or production deployment.
- Production database changes must not be performed manually without a documented reason and recovery plan.
- Existing business records must be preserved during schema changes.
  16.2 Migration Version Tracking
  The plugin must maintain a database schema version to identify which migrations have been applied.
- Store the installed schema version using an appropriate WordPress option or dedicated version-tracking mechanism.
- Compare the installed version with the plugin's required schema version.
- Run only migrations that have not already been applied.
- Update the stored schema version only after a migration completes successfully.
- Prevent the same migration from being applied repeatedly.
- Record migration outcomes without logging sensitive information.
  16.3 Migration Safety
  Every migration must:
- Validate required database conditions before execution.
- Preserve existing records and relationships.
- Avoid unintended deletion or overwriting of business data.
- Handle partially completed operations safely.
- Check whether required tables, columns, and indexes already exist where appropriate.
- Report failures clearly.
- Provide a recovery procedure for failed migrations.
- Be tested against representative data before production deployment.
  16.4 Database Compatibility
- Confirm compatibility with the database engine and version used by the target environment.
- Test schema definitions and migration operations against the supported MySQL and MariaDB versions.
- Avoid database-specific features until compatibility has been verified.
- Review differences in data types, JSON support, foreign key behavior, indexes, and SQL syntax.
- Do not assume that successful execution in local MySQL guarantees compatibility with production MariaDB.
- Resolve the production database compatibility requirements before implementing the final schema.
  16.5 Migration Testing
  Before deployment, verify:
- All required tables are created successfully.
- Required columns and data types are correct.
- Primary keys and unique constraints behave as expected.
- Foreign key relationships work where implemented.
- Indexes are created correctly.
- Existing records remain intact.
- Application queries work against the migrated schema.
- Failed migrations can be diagnosed and recovered.
- Re-running an already completed migration does not duplicate or damage schema objects.
  16.6 Backup and Recovery
- Create a verified database backup before applying migrations to staging or production.
- Confirm that the backup can be restored before relying on it.
- Keep backups separate from the production environment.
- Document the restoration procedure.
- Do not apply production migrations without an appropriate backup and recovery plan.
- Verify database integrity after restoration.
  16.7 Migration Documentation
  Each migration must document:
- Migration version.
- Purpose of the change.
- Tables, columns, indexes, or constraints affected.
- Data transformations, where applicable.
- Compatibility requirements.
- Preconditions.
- Expected result.
- Recovery considerations.
  16.8 Deployment Rules
- Apply migrations to local development first.
- Validate the application against the updated schema.
- Apply migrations to staging and complete testing.
- Apply production migrations only after backup, compatibility, and deployment checks are satisfied.
- Record the migration version and outcome for each environment.
- Do not make direct production schema changes outside the approved migration process.
17. Implementation Readiness Checklist
    This section defines the conditions that must be satisfied before implementing the database schema in the Blue Sheep Business Manager plugin.
The schema is a logical design specification. It is not production-ready SQL DDL. Database tables and migrations must not be created until the target database version, character set, collation, structured-data storage strategy, and migration compatibility have been verified.
17.1 Table Completeness
Confirm that all eleven custom database tables are documented:
- bs_customers
- bs_enquiries
- bs_quotations
- bs_quotation_versions
- bs_quotation_items
- bs_bookings
- bs_payments
- bs_documents
- bs_access_tokens
- bs_activity_log
- bs_notification_log
  17.2 Relationship Verification
  Before implementation, verify:
- Every direct relationship references the correct primary key.
- Customer ownership is consistent across related records.
- Each quotation version belongs to the correct quotation.
- Each quotation item belongs to the correct version.
- Each booking references the exact accepted quotation version.
- Each payment belongs to the correct booking.
- Refunds reference their original verified receipts.
- Documents reference the correct customer and optional quotation or booking.
- Polymorphic references are validated by application logic.
- WordPress user and content references are validated against WordPress records.
  17.3 Business Rule Verification
  Confirm that the schema supports the approved business workflows:
- Custom trip enquiries and fixed package reservation requests.
- Multiple quotation versions and immutable accepted versions.
- Day-by-day itineraries and itemised quotation pricing.
- Flexible travel dates and traveller counts.
- Booking confirmation after payment verification or an authorized exception.
- Multiple receipts and refunds with payment verification.
- Multi-currency quotations and payment reconciliation.
- Secure guest access to quotations, bookings, and documents.
- Activity history and notification delivery tracking.
  17.4 Database Compatibility Verification
  Before writing migrations:
- Confirm the database engine and version used in the target hosting environment.
- Compare production compatibility requirements with the local MySQL environment.
- Verify supported column types, indexes, constraints, and SQL operations.
- Decide how structured enquiry data will be stored. If MariaDB 10.0.32 remains the target, use a compatible text-based representation such as LONGTEXT containing validated JSON, rather than assuming native JSON behavior. Validate and encode/decode the content in the application.
- Confirm the target character set and collation, including utf8mb4 support and index-length implications for the selected engine/version.
- Verify foreign key support and deletion behavior.
- Confirm the supported character set and collation.
- Resolve compatibility issues before approving the implementation schema.
  17.5 Security Verification
  Confirm that:
- Private documents are stored outside publicly accessible storage.
- Access tokens are generated securely and only their hashes are stored.
- Token scope, expiry, and revocation are enforced.
- Financial records cannot be silently overwritten or deleted.
- Accepted quotation versions remain immutable.
- Sensitive credentials and unnecessary personal information are excluded from logs.
- WordPress capabilities and application-level authorization protect private operations.
- Database queries use prepared statements.
- All inputs are validated and outputs appropriately escaped.
  17.6 Migration and Recovery Verification
  Before production implementation:
- Define the migration versioning mechanism.
- Test migrations in the local environment.
- Establish a staging environment.
- Create and verify a database backup.
- Document the restoration procedure.
- Test migration failure handling and recovery.
- Confirm that existing records and relationships are preserved.
- Verify that migrations can be safely applied without unintended duplication.
  17.7 Final Approval
  The database schema may be approved for implementation only after:
- All eleven table definitions have been reviewed.
- Relationships and constraints have been checked.
- The target database engine/version, character set, collation, and structured-data storage approach have been confirmed and tested.
- Security requirements have been verified.
- Migration and recovery requirements have been documented.
- Outstanding business and operational decisions have been resolved or explicitly recorded.
  Approval of this schema authorizes database implementation planning. It does not authorize changes to the production database.
  Appendix A. Decisions and Items Requiring Confirmation Before SQL Implementation
  The following items are intentionally not guessed in this logical schema:
1. Production database target: Confirm whether the hosting provider supports an upgrade from MariaDB 10.0.32. If not, explicitly approve continued compatibility with that version and test against it.
2. PHP runtime compatibility: The existing production environment uses PHP 7.3.33. Confirm the supported PHP version and plan the PHP upgrade/compatibility path before plugin implementation. Do not assume local PHP 8.2 behavior is available in production.
3. Structured enquiry data: Use a validated JSON representation. If the legacy MariaDB version remains in scope, store it in a compatible text column such as LONGTEXT, not a native JSON column.
4. Character set and collation: Confirm the actual database defaults and choose a supported utf8mb4 character set/collation after checking index-length constraints.
5. Currency rounding: Confirm currency-specific decimal handling and the rounding mode with the business before implementing financial calculations.
6. Payment evidence channel: The logical design permits evidence submitted through WhatsApp/email or a protected website upload. The final operational choice can be configured later. Evidence submission never verifies a payment automatically.
7. Notification attempt detail: The approved design contains eleven tables. bs_notification_log stores attempt count and latest-attempt metadata, not one immutable row per attempt. A dedicated attempt-history table requires an explicit architecture change.
8. Retention periods: Retention and anonymization durations require review against applicable Bhutanese legal, tax, and accounting requirements. No retention duration is invented here.
9. Protected document storage: Select and test the final storage mechanism, access control, backup, and restoration procedure before generating private business documents.
   Appendix B. Implementation Guardrails
   - Use prepared database queries and application-level authorization for every private operation.
   - Validate ownership across customer, quotation, booking, document, token, and payment relationships.
   - Use transactions for related business operations where supported, and design safe recovery for operations that cannot be made atomic.
   - Do not assume all DDL statements are transactionally reversible. MySQL/MariaDB schema changes may cause implicit commits; migrations must use explicit preconditions, postconditions, and recovery procedures.
   - Do not expose sequential database IDs, file paths, token hashes, or private document URLs to customers.
   - Never store raw access tokens. Store only cryptographic hashes.
   - Do not store card numbers, banking login credentials, or payment gateway secrets.
   - Keep production changes blocked until a verified backup, tested staging environment, and recovery procedure exist.
     Document status: Revised logical schema specification, pending production database compatibility confirmation. This document does not authorize production database changes.