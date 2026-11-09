\# Database Architecture



\*\*Project:\*\* Bhutan Blue Sheep Tours \& Travels

\*\*Document:\*\* Database Architecture

\*\*Status:\*\* Proposed design, pending final review

\*\*Version:\*\* 1.1

\*\*Last Updated:\*\* 9 November 2026



\---



\## 1. Purpose



This document defines the proposed database architecture for the Bhutan Blue Sheep Tours \& Travels website and its business-management system.



The architecture separates public website content from private business records to improve security, maintainability, and data management.



This document establishes the database design baseline. The schema must not be implemented until the outstanding decisions in Section 11 have been resolved and this document has received final approval.



\## 2. Database Strategy



The application will use the existing WordPress database.



Two storage approaches will be used:



\### 2.1 WordPress Content Storage



WordPress Custom Post Types will manage public website content:



\- Tours

\- Destinations

\- Experiences

\- Travel Journal articles

\- Testimonials



WordPress taxonomies will organize categories, regions, travel styles, and other classifications.



\### 2.2 Custom Database Tables



Dedicated database tables will manage private business records:



\- Customers

\- Enquiries

\- Quotations

\- Quotation versions

\- Quotation items

\- Bookings

\- Payments

\- Documents

\- Customer access tokens

\- Activity logs



All custom tables will use the active WordPress database prefix dynamically.



Tables will use the InnoDB storage engine to support transactions and foreign-key relationships where appropriate. Foreign keys will only be used where the referenced tables and deletion rules support them. Relationships to WordPress content and polymorphic references must be validated by the application if a conventional foreign key is not appropriate.



\## 3. Proposed Database Tables



The fields below describe the proposed logical schema. Exact SQL types, lengths, nullability, indexes, constraints, and default values must be specified during schema design.



\### 3.1 Customers: `bs\_customers`



Stores customer contact information.



Proposed fields:



\- `id`: Primary key.

\- `full\_name`: Customer name.

\- `email`: Email address.

\- `phone`: Phone or WhatsApp number.

\- `country`: Country of residence.

\- `created\_at`: Record creation timestamp.

\- `updated\_at`: Last modification timestamp.



Customer records should be reusable across multiple enquiries and bookings. Email addresses should not automatically be treated as unique customer identifiers because customers may share or change email addresses.



\### 3.2 Enquiries: `bs\_enquiries`



Stores custom trip enquiries, package reservation requests, and general enquiries.



Proposed fields:



\- `id`: Primary key.

\- `customer\_id`: Associated customer.

\- `enquiry\_type`: Custom trip, package reservation, or general enquiry.

\- `tour\_id`: Optional reference to a WordPress tour.

\- `preferred\_start\_date`: Requested travel start date.

\- `preferred\_end\_date`: Requested travel end date.

\- `dates\_flexible`: Indicates whether dates are flexible.

\- `traveller\_count`: Total number of travellers.

\- `adults\_count`: Number of adult travellers, where applicable.

\- `children\_count`: Number of child travellers, where applicable.

\- `destinations`: Requested destinations, stored as structured JSON data.

\- `interests`: Travel preferences, stored as structured JSON data.

\- `budget\_range`: Optional budget information.

\- `additional\_requirements`: Additional customer information.

\- `status`: Current enquiry status. Store one of the application-defined values listed below.

\- `created\_at`: Submission timestamp.

\- `updated\_at`: Last modification timestamp.



The application must validate that traveller counts are consistent when the individual adult and child counts are supplied. General enquiries may leave trip-specific fields empty.



\#### Enquiry status values



Use these eight application-level status values:



| Stored value | Display label | Meaning |

|---|---|---|

| `new` | New | A customer has submitted an enquiry that has not yet been reviewed. |

| `under\_review` | Under Review | An administrator is reviewing the enquiry. |

| `awaiting\_customer\_information` | Awaiting Customer Information | The business needs additional information from the customer before proceeding. |

| `in\_quotation` | In Quotation | The administrator is preparing or revising a quotation for the enquiry. |

| `quoted` | Quoted | A quotation has been sent to the customer. This does not mean the quotation has been accepted or the booking confirmed. |

| `converted` | Converted | The enquiry has resulted in a booking record linked to the accepted quotation version. The booking may still be awaiting payment verification or confirmation. |

| `declined` | Declined | The business or customer has decided not to proceed with the enquiry. |

| `closed` | Closed | The enquiry has been closed without creating a booking. |



\#### Permitted enquiry status transitions



The application must enforce the following transitions:



| Current status | Permitted next status |

|---|---|

| `new` | `under\_review`, `declined`, `closed` |

| `under\_review` | `awaiting\_customer\_information`, `in\_quotation`, `declined`, `closed` |

| `awaiting\_customer\_information` | `under\_review`, `declined`, `closed` |

| `in\_quotation` | `quoted`, `awaiting\_customer\_information`, `declined`, `closed` |

| `quoted` | `in\_quotation`, `converted`, `declined`, `closed` |

| `converted` | No further enquiry status changes |

| `declined` | No further enquiry status changes |

| `closed` | No further enquiry status changes |



Transition rules:



\- A newly submitted enquiry starts with `new`.

\- An administrator moves an enquiry to `under\_review` when review begins.

\- Use `awaiting\_customer\_information` when the business has requested information needed to assess the request or prepare a quotation. When the information is received, return the enquiry to `under\_review`.

\- Move an enquiry to `in\_quotation` when quotation preparation or revision begins.

\- Move an enquiry to `quoted` only after the quotation has actually been sent to the customer.

\- If the customer requests changes to a sent quotation, move the enquiry from `quoted` to `in\_quotation` while creating a new quotation version. Preserve the previously sent version.

\- Move an enquiry to `converted` only when an accepted quotation has produced a linked booking record. This transition does not itself confirm the booking.

\- A booking must follow its own payment-verification and confirmation workflow. An enquiry marked `converted` must not be interpreted as a confirmed booking.

\- `declined` and `closed` are terminal statuses. If the customer later makes a new request, create a new enquiry and retain the original record for history.

\- Record each status change with the previous status, new status, timestamp, acting administrator or system process, and an appropriate reason where applicable.

\- Status changes must be validated by the application. Do not allow arbitrary status updates that bypass these transitions.



\### 3.3 Quotations: `bs\_quotations`



Stores quotation master records.



Proposed fields:



\- `id`: Primary key.

\- `enquiry\_id`: Related enquiry.

\- `customer\_id`: Associated customer.

\- `reference\_number`: Unique quotation reference.

\- `current\_version\_id`: Current quotation version.

\- `status`: Lifecycle status of the current quotation version. This value must reflect the status of the version referenced by `current\_version\_id`.

\- `created\_by`: Administrator who created the quotation.

\- `created\_at`: Creation timestamp.

\- `updated\_at`: Last modification timestamp.



The quotation master record identifies the overall quotation. Its `status` field must stay synchronized with the version referenced by `current\_version\_id`.



The quotation reference must not expose customer personal information or rely on a predictable public database ID.



\### 3.4 Quotation Versions: `bs\_quotation\_versions`



Preserves quotation history and the exact terms presented to the customer.



Proposed fields:



\- `id`: Primary key.

\- `quotation\_id`: Parent quotation.

\- `version\_number`: Sequential version number within the quotation.

\- `status`: Lifecycle status of this quotation version, using one of the application-defined values below.

\- `currency`: Quotation currency.

\- `base\_currency`: Internal reference currency, normally BTN.

\- `exchange\_rate`: Exchange rate used when converting from the base currency to the quotation currency.

\- `exchange\_rate\_source`: Source of the exchange rate, if applicable.

\- `exchange\_rate\_date`: Date the exchange rate was applied.

\- `subtotal`: Quotation subtotal.

\- `discount`: Discount amount.

\- `total\_amount`: Final quotation amount.

\- `deposit\_type`: Fixed amount or percentage.

\- `deposit\_value`: Required deposit.

\- `balance\_due\_date`: Applicable balance deadline.

\- `cancellation\_terms`: Agreed cancellation policy.

\- `terms\_and\_conditions`: Quotation terms.

\- `valid\_until`: Quotation expiry date.

\- `accepted\_at`: Acceptance timestamp.

\- `created\_at`: Version creation timestamp.



For a conversion from the base currency to the quotation currency, `exchange\_rate` should have one documented direction: the number of quotation-currency units equivalent to one unit of base currency. When no conversion is required, the exchange-rate fields may be empty or use the agreed no-conversion representation.



An accepted quotation version must remain unchanged. Revisions must create a new version. The system must preserve the exact version accepted by the customer, including its terms, amounts, currency, itinerary, and items.



\#### Quotation version status values



Use these seven application-level status values for each quotation version:



| Stored value | Display label | Meaning |

|---|---|---|

| `draft` | Draft | The version is being prepared and has not been sent to the customer. |

| `sent` | Sent | The version has been sent to the customer and is awaiting a response. |

| `changes\_requested` | Changes Requested | The customer has requested changes to this sent version. |

| `accepted` | Accepted | The customer has accepted this exact version and its terms. |

| `declined` | Declined | The customer has declined this version. |

| `expired` | Expired | The version validity period ended before the customer accepted it. |

| `superseded` | Superseded | A newer version replaces this version, or the administrator has withdrawn it in favor of a replacement. |



\#### Permitted quotation version status transitions



| Current status | Permitted next status |

|---|---|

| `draft` | `sent`, `superseded` |

| `sent` | `changes\_requested`, `accepted`, `declined`, `expired`, `superseded` |

| `changes\_requested` | `superseded` |

| `accepted` | No further status changes |

| `declined` | No further status changes |

| `expired` | No further status changes |

| `superseded` | No further status changes |



Transition rules:



\- A new quotation version starts as `draft`.

\- Move a version to `sent` only after it has actually been delivered to the customer.

\- A customer response requesting modifications changes the sent version to `changes\_requested`. Create a separate new version in `draft` for the requested changes. Once the replacement version is ready to be sent, mark the prior version `superseded`.

\- If the customer accepts a version, mark that exact version `accepted`. Do not edit its contents or terms after acceptance.

\- A version can be marked `declined` only when the customer has declined that version.

\- Mark a version `expired` when its `valid\_until` date/time has passed without acceptance, subject to the application's defined timezone and expiry processing rules.

\- Mark a version `superseded` when it is replaced or withdrawn in favor of another version. Preserve the old version and its quotation items for audit history.

\- Only one version of a quotation may be the accepted version. The application must prevent acceptance of a different version after one version has already been accepted, unless a separately approved business process explicitly permits it.

\- Acceptance must record the accepted version, acceptance timestamp, and the method or evidence of acceptance. The customer must be shown the terms of the exact version being accepted.

\- The quotation master `status` must reflect the status of the version referenced by `current\_version\_id`. When a new version becomes current, update the current-version reference and master status consistently.

\- Record every status transition with the previous status, new status, timestamp, actor or system process, and an appropriate reason where applicable.

\- Validate all transitions in the application. Do not allow arbitrary status updates that bypass these rules.



A quotation being `accepted` does not by itself mean that the booking is confirmed. Booking confirmation follows the separate booking and payment-verification workflow.



\### 3.5 Quotation Items: `bs\_quotation\_items`



Stores itinerary components and itemised quotation costs.



Proposed fields:



\- `id`: Primary key.

\- `quotation\_version\_id`: Associated quotation version.

\- `item\_type`: Accommodation, transport, activity, guide, meal, or other.

\- `day\_number`: Relevant itinerary day, where applicable.

\- `title`: Item title.

\- `description`: Item description.

\- `quantity`: Item quantity.

\- `unit\_amount`: Cost per unit.

\- `total\_amount`: Item total.

\- `sort\_order`: Display sequence.



Quotation items are tied to a specific quotation version. Once a quotation version has been sent to a customer, its items must not be edited or deleted. Any changes to the itinerary, descriptions, quantities, or costs must be made in a new quotation version. Draft items may be edited before the quotation version is sent.



\### 3.6 Bookings: `bs\_bookings`



Stores confirmed bookings.



Proposed fields:



\- `id`: Primary key.

\- `customer\_id`: Associated customer.

\- `quotation\_id`: Accepted quotation.

\- `accepted\_version\_id`: Exact accepted quotation version.

\- `booking\_reference`: Unique booking reference.

\- `travel\_start\_date`: Confirmed start date.

\- `travel\_end\_date`: Confirmed end date.

\- `traveller\_count`: Confirmed number of travellers.

\- `status`: Current booking status.

\- `confirmed\_at`: Confirmation timestamp.

\- `created\_at`: Creation timestamp.

\- `updated\_at`: Last modification timestamp.



A booking is not automatically confirmed when a reservation request is submitted. Booking confirmation must follow the agreed business rules and the administrator's verification of the required payment, unless an authorized administrator records an approved exception with an audit history.



The booking must retain a reference to the exact accepted quotation version.



\#### Booking status values



Use these five application-level status values:



| Stored value | Display label | Meaning |

|---|---|---|

| `pending\_payment` | Pending Payment | The customer has accepted the quotation and a booking record exists, but the required deposit has not yet been verified or an authorized exception has not been recorded. |

| `confirmed` | Confirmed | The business has verified the required deposit or recorded an authorized confirmation exception. |

| `in\_progress` | In Progress | The customer's trip has started. |

| `completed` | Completed | The trip has ended and the booking is operationally complete. |

| `cancelled` | Cancelled | The booking has been cancelled and is no longer active. |



\#### Permitted booking status transitions



| Current status | Permitted next status |

|---|---|

| `pending\_payment` | `confirmed`, `cancelled` |

| `confirmed` | `in\_progress`, `cancelled` |

| `in\_progress` | `completed`, `cancelled` |

| `completed` | No further status changes |

| `cancelled` | No further status changes |



Transition rules:



\- A booking created from an accepted quotation starts as `pending\_payment`.

\- Move a booking to `confirmed` only after an administrator verifies the required deposit, or records an authorized exception with a reason and audit entry.

\- Move a booking to `in\_progress` when the trip has actually started.

\- Move a booking to `completed` after the trip has ended and the business has completed the booking.

\- Cancellation must record the acting administrator, timestamp, reason, and any applicable cancellation or refund outcome.

\- `completed` and `cancelled` are terminal statuses. If the customer later makes a new reservation, create a new enquiry and booking rather than reopening the old booking.

\- Do not infer booking confirmation from quotation acceptance, enquiry conversion, or a payment record that is still pending verification.

\- Record every status transition with the previous status, new status, timestamp, actor or system process, and an appropriate reason where applicable.

\- Validate all transitions in the application. Do not allow arbitrary status updates that bypass these rules.



\### 3.7 Payments: `bs\_payments`



Stores manually recorded payments.



Proposed fields:



\- `id`: Primary key.

\- `booking\_id`: Associated booking.

\- `payment\_type`: `receipt` or `refund`.

\- `related\_payment\_id`: Optional reference to the original verified receipt when this record is a refund.

\- `amount`: Amount received or refunded, stored as a positive amount; `payment\_type` determines its meaning.

\- `currency`: Currency actually received or refunded.

\- `quotation\_currency\_equivalent`: Amount expressed in the booking quotation currency for balance calculations, where conversion is required.

\- `exchange\_rate`: Exchange rate applied to convert the payment currency to the quotation currency, where required.

\- `exchange\_rate\_source`: Source of the applied exchange rate, where applicable.

\- `exchange\_rate\_date`: Date the applied exchange rate was obtained or fixed.

\- `payment\_method`: Bank transfer, cash, or another approved external method.

\- `payment\_date`: Date received or refunded.

\- `reference`: Optional transaction reference.

\- `verification\_status`: `pending\_verification`, `verified`, or `rejected`.

\- `verified\_by`: Administrator who verified the payment.

\- `verified\_at`: Verification timestamp.

\- `created\_at`: Record creation timestamp.



Payment records must be retained for audit purposes. Rejected payments must not be treated as received funds. Corrections must preserve the original record and activity history rather than silently overwriting or deleting the original record.



A refund must be recorded as a separate `refund` record linked to the original verified receipt. Do not edit the original receipt to make it appear that the funds were never received. The application must prevent verified refunds from exceeding the remaining refundable amount of the original receipt. Booking payment totals must include verified receipts less verified refunds, using the applicable currency-conversion policy.



\#### Payment verification status values



Use these three application-level verification status values:



| Stored value | Display label | Meaning |

|---|---|---|

| `pending\_verification` | Pending Verification | The payment or refund has been recorded but has not yet been checked by an administrator. |

| `verified` | Verified | An administrator has checked the external transaction and confirmed the receipt or refund. |

| `rejected` | Rejected | The submitted payment or refund record could not be verified and must not affect the booking balance. |



\#### Permitted payment verification status transitions



| Current status | Permitted next status |

|---|---|

| `pending\_verification` | `verified`, `rejected` |

| `verified` | No further status changes |

| `rejected` | No further status changes |



Transition rules:



\- Every new receipt or refund record starts as `pending\_verification`.

\- Only an authorized administrator may verify or reject a record.

\- A verified record is immutable. If a verified record needs correction, retain it and create an auditable correcting record.

\- A rejected record remains in the audit history. If the customer supplies corrected evidence, create a new payment record rather than reopening or overwriting the rejected one.

\- A refund record must reference its original verified receipt and must not exceed the remaining refundable amount.

\- Record the verifier, verification timestamp, and reason for rejection or correction.

\- Only verified receipts count as funds received. Only verified refunds reduce the net amount received.

\- Validate all transitions in the application. Do not allow arbitrary status updates that bypass these rules.



No card numbers, banking credentials, or online payment information will be stored.



Payments may be received in a currency different from the quotation currency. Store the amount and currency actually received, and store the conversion rate, rate date, and source used for booking-balance calculations when conversion is required. The calculation and rounding rules are defined in Section 7.



\### 3.8 Documents: `bs\_documents`



Stores references to generated business documents.



Proposed fields:



\- `id`: Primary key.

\- `customer\_id`: Associated customer.

\- `quotation\_id`: Optional quotation reference.

\- `booking\_id`: Optional booking reference.

\- `document\_type`: Quotation, invoice, booking confirmation, receipt, or other.

\- `file\_reference`: Protected file reference, not a publicly accessible URL or an exposed physical server path.

\- `created\_at`: Creation timestamp.



Private documents must not be exposed through publicly accessible upload URLs.



Document storage and access rules:



\- Store private documents outside the publicly accessible uploads directory, or use an equivalent protected storage mechanism.

\- Serve documents only through authenticated or securely authorized download endpoints.

\- Guest access must use secure, random, expiring, and revocable access tokens.

\- Verify the user's permissions or access token before serving a document.

\- Do not expose physical server paths or predictable file locations.

\- Expired or revoked access links must no longer work.

\- The final storage mechanism and operational backup procedure must be selected before implementation.



\### 3.9 Customer Access Tokens: `bs\_access\_tokens`



Stores token hashes and access-control metadata for guest access to quotations, bookings, or documents.



Proposed fields:



\- `id`: Primary key.

\- `token\_hash`: Hash of the generated access token. The original usable token must never be stored.

\- `customer\_id`: Customer to whom the access is granted.

\- `entity\_type`: Resource type, such as quotation, booking, or document.

\- `entity\_id`: ID of the specific resource.

\- `expires\_at`: Token expiry timestamp.

\- `revoked\_at`: Revocation timestamp, empty while the token remains active.

\- `last\_used\_at`: Most recent successful use, where tracked.

\- `created\_at`: Token creation timestamp.

\- `created\_by`: Administrator who generated the token, where applicable.



Tokens must be generated using a cryptographically secure random generator. The application must hash the token before storage, validate the presented token securely, enforce its resource scope, and reject expired or revoked tokens. Tokens must grant access only to the intended resource and must not provide general customer-account access.



\### 3.10 Activity Logs: `bs\_activity\_log`



Records important administrative and business actions.



Proposed fields:



\- `id`: Primary key.

\- `user\_id`: WordPress administrator ID, where applicable.

\- `entity\_type`: Type of affected record.

\- `entity\_id`: Affected record ID.

\- `action`: Action performed.

\- `description`: Brief activity description.

\- `created\_at`: Event timestamp.



Sensitive information, passwords, access tokens, payment credentials, and unnecessary personal information must never be written to logs.



Because `entity\_type` and `entity\_id` can refer to different business tables, this is a polymorphic reference. The application must validate the referenced record. A conventional foreign key cannot directly enforce this relationship across multiple tables.



\### 3.11 Notification Logs: `bs\_notification\_log`



Stores limited delivery metadata for operational email notifications.



Proposed fields:



\- `id`: Primary key.

\- `event\_type`: Notification event, such as enquiry acknowledgement, quotation delivery, or booking update.

\- `related\_entity\_type`: Related business record type, where applicable.

\- `related\_entity\_id`: Related business record ID, where applicable.

\- `recipient\_reference`: Recipient address or protected reference, subject to the data-minimization policy.

\- `delivery\_status`: `queued`, `sent`, `failed`, or `retrying`.

\- `provider\_reference`: Optional message identifier returned by the email provider.

\- `attempt\_count`: Number of delivery attempts.

\- `last\_attempt\_at`: Timestamp of the latest attempt.

\- `created\_at`: Log creation timestamp.



Do not store email passwords, SMTP credentials, access tokens, full message bodies, or unnecessary personal information in notification logs. Email content must be generated from approved templates and the underlying business record.



\## 4. Entity Relationships



The primary relationships are:



\- One customer can have multiple enquiries.

\- One enquiry can have multiple quotation records.

\- One quotation can have multiple versions.

\- One quotation version can contain multiple itinerary and cost items.

\- An accepted quotation version can produce a booking.

\- One booking can have multiple payment receipt and refund records.

\- A refund payment record references the original verified receipt.

\- One customer can have multiple bookings.

\- Documents can be associated with customers, quotations, and bookings.

\- Customer access tokens can be scoped to a customer and a specific quotation, booking, or document.

\- Activity logs can reference different business records.

\- Notification logs can reference the business event or record that triggered a notification.



\### 4.1 Relationship and Deletion Policies



The implementation must follow these rules:



\- Do not use cascading deletion for financial or booking records.

\- Prevent deletion of customers who have associated bookings or financial records.

\- Preserve accepted quotations, quotation versions, bookings, and payment history.

\- Use soft deletion or anonymization where appropriate instead of permanently deleting business records.

\- Restrict deletion of records referenced by other business records.

\- Customer and enquiry retention and deletion must follow the approved data retention policy.

\- Deleting a WordPress administrator account must not automatically delete business activity history.

\- Revoke relevant customer access tokens when their access is no longer required, when a resource is withdrawn, or when an administrator revokes access.



\### 4.2 WordPress Content References



The `tour\_id` field in `bs\_enquiries` refers to a WordPress tour. The implementation must validate that the referenced post exists and is the expected public content type. The exact database constraint strategy must be decided during schema design. Deleting or unpublishing a tour must not delete historical enquiries or bookings that refer to it.



\## 5. Data Integrity



The implementation must:



\- Use primary keys for all custom tables.

\- Use appropriate foreign-key relationships where technically and operationally suitable.

\- Validate application-managed and polymorphic references.

\- Add indexes to frequently queried fields and relationship columns.

\- Enforce unique quotation and booking reference numbers.

\- Enforce unique token hashes.

\- Preserve historical quotation versions and their items.

\- Prevent invalid enquiry, quotation, booking, and payment state transitions.

\- Use database transactions for operations involving multiple related records where supported.

\- Use UTC timestamps internally and display dates in the configured Bhutan timezone.

\- Avoid floating-point types for monetary values. Use fixed-precision decimal storage and decimal-safe calculations.



\## 6. Privacy and Security



\- Restrict private records to authorized administrators.

\- Use WordPress capabilities to control access.

\- Validate and sanitize input, and escape output in the appropriate context.

\- Use prepared database queries.

\- Protect administrative operations with nonce verification where appropriate.

\- Do not expose private records through public REST endpoints.

\- Generate customer access tokens using a cryptographically secure random generator.

\- Store only token hashes in the database, never the original usable tokens.

\- Enforce token expiry and revocation.

\- Validate the token and its permissions before granting access.

\- Limit tokens to their intended customer and specific quotation, booking, or document.

\- Store only necessary customer information.

\- Establish data retention and deletion procedures.

\- Protect generated documents with access-controlled storage.

\- Maintain secure backups stored separately from the production server.

\- Do not collect or store passport scans, card details, banking credentials, or unnecessary identity documents.



\## 7. Currency Handling



The system must support multiple quotation and payment currencies.



\- BTN will be the default internal business currency.

\- USD, EUR, INR, and other required currencies can be supported.

\- Store the ISO 4217 currency code with every monetary amount.

\- Store monetary amounts in `DECIMAL(19,4)` fields. Do not use floating-point database types or binary floating-point arithmetic for money.

\- Store exchange rates in `DECIMAL(20,10)` fields.

\- Use currency-specific minor-unit precision for customer-facing display and rounding. The stored four-decimal precision is for calculation and retention, not a rule that every currency must display four decimal places.

\- Preserve the quotation currency, base currency, exchange rate, exchange-rate direction, source, and rate date used when a quotation is issued.

\- Define the exchange-rate direction consistently as quotation-currency units per one unit of base currency.

\- Do not recalculate historical quotations using current exchange rates.

\- Record payments and refunds in the currency actually received or returned.

\- When payment currency differs from quotation currency, preserve the conversion rate, source, date, and calculated quotation-currency equivalent used for the booking balance.

\- Calculate balances using decimal-safe arithmetic and apply the currency's defined rounding rule at the appropriate calculation boundary.

\- Do not silently change a previously issued quotation or historical payment calculation when exchange-rate settings change.



The application must maintain a documented currency metadata list that identifies the supported currency code and its minor-unit precision. The business owner must approve the supported currencies and rounding policy before financial calculations are implemented.



\## 8. Database Naming Convention



Custom table names will follow:



`{wordpress\_prefix}bs\_{table\_name}`



Examples:



\- `wp\_bs\_customers`

\- `wp\_bs\_enquiries`

\- `wp\_bs\_quotations`

\- `wp\_bs\_quotation\_versions`

\- `wp\_bs\_quotation\_items`

\- `wp\_bs\_bookings`

\- `wp\_bs\_payments`

\- `wp\_bs\_documents`

\- `wp\_bs\_access\_tokens`

\- `wp\_bs\_activity\_log`

\- `wp\_bs\_notification\_log`



The examples use `wp\_` for illustration only. The actual WordPress prefix must be determined dynamically during plugin installation.



\## 9. Implementation Considerations



The database will be created and maintained through the Blue Sheep Business Manager plugin.



The plugin must include:



\- Database installation routines.

\- Schema version tracking.

\- Controlled database migrations.

\- Index creation and validation.

\- Safe upgrade procedures.

\- Appropriate foreign-key handling.

\- Backup and restoration procedures.

\- Preservation of existing business records during schema upgrades.

\- Database transactions for migrations where supported.

\- Migration result and error logging without recording sensitive information.

\- A recovery procedure for failed migrations.

\- Database integrity checks after installation and upgrades.



Database changes must be tested locally before deployment.



\### 9.1 Migration Safety Requirements



\- Take and verify an appropriate database backup before applying a production schema migration.

\- Do not run a migration against production unless the recovery procedure is understood and available.

\- Make migrations safe to re-run where practical, and track which schema version has been applied.

\- Do not silently drop, truncate, or overwrite existing business records during an upgrade.

\- Where a migration cannot be completed safely, stop the upgrade and report the failure without exposing sensitive information.

\- Verify the resulting schema and critical record relationships after installation or upgrade.



Database transactions must be used where supported. Some database schema operations may not be fully reversible through transactions, so the recovery procedure must also account for restoring from a verified backup.



\## 10. Finalized Database Design Decisions



The following decisions establish the approved database design baseline.



\### 10.1 Status Values and Transitions



\- Enquiry statuses and transitions are defined in Section 3.2.

\- Quotation version statuses and transitions are defined in Section 3.4.

\- Booking statuses and transitions are defined in Section 3.6.

\- Payment verification statuses and transitions are defined in Section 3.7.

\- Status values are application-level constants stored in string columns, not MySQL `ENUM` definitions. This allows controlled changes through versioned application migrations.

\- Every status transition must be validated by the application and recorded in the relevant audit history.



\### 10.2 Reference Number Formats



\- Quotation references use the format `Q-YYYY-RANDOM`, for example `Q-2026-7K4M9P2X6D8R`.

\- Booking references use the format `B-YYYY-RANDOM`, for example `B-2026-3N8W5C1J7F2A`.

\- `YYYY` is the year the reference is created.

\- `RANDOM` is a cryptographically generated, non-sequential identifier with at least 12 uppercase alphanumeric characters.

\- References must not contain customer names, email addresses, phone numbers, travel dates, or sequential database IDs.

\- Enforce a unique database index on each reference. If a collision occurs, generate a new random value.

\- Public references identify records but do not authorize access. Secure access tokens remain separate.



\### 10.3 Customer Document Storage



\- Store private business documents outside the public web root wherever the hosting environment permits.

\- If storage outside the web root is not supported, use an equivalent protected storage mechanism with direct web access explicitly denied.

\- Serve documents only through application endpoints that verify administrator permissions or a valid, scoped guest token.

\- Use opaque file references rather than public file URLs or predictable names.

\- Back up private documents using the same protection and recovery standards as the database.

\- Do not store passport scans, card details, banking credentials, or unnecessary identity documents.



The specific filesystem or storage provider will be selected after the hosting environment is confirmed. This does not change the access-control requirements above.



\### 10.4 Foreign-Key and Deletion Policies



\- Use InnoDB for custom business tables.

\- Use foreign keys with restrictive deletion behavior for relationships between custom business tables where supported and appropriate.

\- Do not use cascading deletion for customers, enquiries, quotations, quotation versions, bookings, payments, or documents.

\- Preserve financial and booking history. Use controlled anonymization or soft deletion only when permitted by the approved retention policy.

\- A draft quotation and its draft items may be removed only through an explicit application operation that checks that the version has never been sent or accepted. Sent or accepted quotation history must be preserved.

\- Treat references to WordPress posts and WordPress users as application-managed references unless a compatible, safe foreign-key design is explicitly verified. Deleting a tour must not delete historical enquiries or bookings. Deleting an administrator must not erase audit history.

\- For activity logs, preserve an actor identifier or actor label snapshot so the historical action remains understandable if the WordPress user is later removed.

\- Revoke access tokens when their resource is withdrawn, their purpose ends, or an administrator revokes access.



\### 10.5 Currency Precision and Exchange Rates



\- Monetary values use `DECIMAL(19,4)` storage.

\- Exchange rates use `DECIMAL(20,10)` storage.

\- Every monetary amount includes its currency code.

\- Currency-specific minor-unit precision governs display and rounding.

\- Exchange-rate direction is quotation-currency units per one unit of base currency.

\- Historical quotations and payment conversions retain the rate, source, and date originally applied.

\- Payments and refunds are recorded in the currency actually received or returned.

\- If a payment currency differs from the booking currency, retain the conversion information and quotation-currency equivalent used for balance calculations.



\### 10.6 Data Retention and Deletion



\- Retention periods must be configurable by record category rather than hard-coded into database schema.

\- Do not automatically delete confirmed booking, accepted quotation, payment, refund, or audit records until the business has approved a retention schedule based on applicable Bhutanese legal, tax, and accounting requirements.

\- Unsuccessful enquiries and unnecessary customer contact details may be deleted or anonymized only after the approved retention period and subject to any legitimate business or legal hold.

\- Expired or revoked access tokens must no longer authorize access. Their minimal audit metadata may be retained according to the approved security-log retention policy.

\- Backups must have a documented retention and secure disposal schedule.

\- Exact retention durations are an operational/legal configuration to be approved with appropriate local advice before automated deletion is enabled. No unsupported legal retention period is assumed in this architecture.



\### 10.7 Activity and Notification Log Retention



\- Activity logs for financial, quotation, booking, payment, refund, and permission changes must be retained with the associated business audit history, subject to the approved retention schedule.

\- Operational notification delivery logs retain only delivery metadata, event type, attempt count, timestamps, and a necessary recipient reference. They must not contain credentials, access tokens, or full message bodies.

\- Notification delivery metadata has a configurable default retention period of 12 months. The period must be reviewed against the business's operational needs and applicable requirements before deployment.

\- Failed notification attempts must be recorded and may be retried according to a controlled retry policy. Retrying must not duplicate a business action such as quotation acceptance or booking creation.



\### 10.8 Implementation and Deployment Gates



Before implementing the schema or enabling automated financial workflows:



\- Confirm the final status transition rules against the business owner's operating process.

\- Obtain approval for supported currencies and rounding behavior.

\- Obtain local advice and approve retention durations.

\- Confirm the production hosting environment supports the selected protected document storage approach.

\- Verify the backup and restoration process.

\- Review the complete SQL schema, indexes, constraints, and migration plan.

\- Test installation, upgrades, failed migrations, restoration, and data integrity locally before staging or production deployment.



\## 11. Architecture Status



The custom database table approach and the database design baseline in this document have been reviewed and approved for the next planning stage.



The approved baseline includes the proposed business tables, relationships, status values and transitions, reference formats, currency storage rules, document access requirements, deletion behavior, and migration safeguards.



This approval authorizes detailed schema specification and local implementation planning. It does not authorize changes to the live production website or production database.



The following operational items remain deployment gates rather than unresolved database architecture choices:



\- Confirm the production hosting environment and protected document storage capability.

\- Obtain appropriate Bhutanese legal/accounting advice and approve exact data-retention durations before enabling automated deletion.

\- Confirm the business owner's final supported currency list and customer-facing rounding policy before implementing financial calculations.

\- Verify a complete backup and restoration procedure before staging or production deployment.



\---



\*\*Implementation rule:\*\* Do not deploy schema changes to production until the operational gates above are satisfied, the detailed SQL schema and migrations have been reviewed, and a verified backup and recovery procedure is available.

