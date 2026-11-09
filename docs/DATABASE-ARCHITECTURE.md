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

\- `status`: Current quotation status, such as draft, sent, accepted, declined, expired, or changes requested.

\- `created\_by`: Administrator who created the quotation.

\- `created\_at`: Creation timestamp.

\- `updated\_at`: Last modification timestamp.



The quotation reference must not expose customer personal information or rely on a predictable public database ID.



\### 3.4 Quotation Versions: `bs\_quotation\_versions`



Preserves quotation history and the exact terms presented to the customer.



Proposed fields:



\- `id`: Primary key.

\- `quotation\_id`: Parent quotation.

\- `version\_number`: Sequential version number within the quotation.

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



\### 3.7 Payments: `bs\_payments`



Stores manually recorded payments.



Proposed fields:



\- `id`: Primary key.

\- `booking\_id`: Associated booking.

\- `amount`: Amount received.

\- `currency`: Currency actually received.

\- `payment\_method`: Bank transfer, cash, or another approved external method.

\- `payment\_date`: Date received.

\- `reference`: Optional transaction reference.

\- `verification\_status`: Pending verification, verified, or rejected.

\- `verified\_by`: Administrator who verified the payment.

\- `verified\_at`: Verification timestamp.

\- `created\_at`: Record creation timestamp.



Payment records must be retained for audit purposes. Rejected payments must not be treated as received funds. Corrections must preserve the original record and activity history rather than silently overwriting or deleting the original record. Where a correction is required, the implementation should record an auditable correction or reversal and the replacement record.



No card numbers, banking credentials, or online payment information will be stored.



The treatment of payments received in a currency different from the quotation currency, including any conversion rate and balance calculation, must be finalized before implementation.



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



\## 4. Entity Relationships



The primary relationships are:



\- One customer can have multiple enquiries.

\- One enquiry can have multiple quotation records.

\- One quotation can have multiple versions.

\- One quotation version can contain multiple itinerary and cost items.

\- An accepted quotation version can produce a booking.

\- One booking can have multiple payment records.

\- One customer can have multiple bookings.

\- Documents can be associated with customers, quotations, and bookings.

\- Customer access tokens can be scoped to a customer and a specific quotation, booking, or document.

\- Activity logs can reference different business records.



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



The system must support multiple quotation currencies.



\- BTN will be the default internal business currency.

\- USD, EUR, INR, and other required currencies can be supported.

\- Store monetary values using appropriate fixed-precision decimal fields.

\- Store the currency code with every monetary amount.

\- Preserve the currency and exchange-rate information applicable when a quotation is issued.

\- Document the direction and meaning of every stored exchange rate.

\- Do not recalculate historical quotations using current exchange rates.

\- Record payments in the currency actually received.

\- Define how payments in a different currency affect the outstanding balance of a quotation or booking before implementation.



The exact decimal precision and currency-specific rounding rules remain to be finalized.



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



\## 10. Outstanding Design Decisions



The following details must be finalized before the relevant functionality is implemented:



\- Exact status values and permitted transitions for quotations, bookings, and payments. Enquiry statuses and permitted transitions are defined in Section 3.2.

\- Data retention periods, subject to applicable Bhutanese legal and accounting requirements.

\- Quotation reference format.

\- Booking reference format.

\- Final customer document storage mechanism and backup procedure.

\- Exact foreign-key constraints and deletion behavior for each relationship.

\- Currency precision, rounding rules, and exchange-rate handling.

\- How payments received in a different currency affect quotation and booking balances.

\- Notification and activity-log retention.

\- Whether access tokens need additional controls, such as a maximum-use limit or rate limiting.



These decisions should be recorded in this document or in the relevant implementation specification before the affected database structures are finalized.



\## 11. Architecture Status



The custom database table approach has been approved as the architectural direction.



The table definitions and rules in this document form the proposed database design. The document still requires final review and approval before the schema is implemented.



\---



\*\*Implementation rule:\*\* Do not create the production database schema until the outstanding design decisions have been resolved, the schema has been reviewed, and a verified backup and recovery approach is available.

