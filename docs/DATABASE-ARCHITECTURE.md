\# Database Architecture



\## 1. Purpose



This document defines the database architecture for the Bhutan Blue Sheep Tours \& Travels website and its business-management system.



The architecture separates public website content from private business records to improve security, maintainability, and data management.



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

\- Activity logs



All custom tables will use the WordPress database prefix dynamically.



Tables will use the InnoDB storage engine to support transactions and foreign-key relationships where appropriate.



\## 3. Proposed Database Tables



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



Customer records should be reusable across multiple enquiries and bookings.



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

\- `traveller\_count`: Number of travellers.

\- `adults\_count`: Number of adult travellers, where applicable.

\- `children\_count`: Number of child travellers, where applicable.

\- `destinations`: Requested destinations, stored as structured JSON data.

\- `interests`: Travel preferences, stored as structured JSON data.

\- `budget\_range`: Optional budget information.

\- `additional\_requirements`: Additional customer information.

\- `status`: Current enquiry status.

\- `created\_at`: Submission timestamp.

\- `updated\_at`: Last modification timestamp.



\### 3.3 Quotations: `bs\_quotations`



Stores quotation master records.



Proposed fields:



\- `id`: Primary key.

\- `enquiry\_id`: Related enquiry.

\- `customer\_id`: Associated customer.

\- `reference\_number`: Unique quotation reference.

\- `current\_version\_id`: Current quotation version.

\- `status`: Draft, sent, accepted, declined, expired, or changes requested.

\- `created\_by`: Administrator who created the quotation.

\- `created\_at`: Creation timestamp.

\- `updated\_at`: Last modification timestamp.



\### 3.4 Quotation Versions: `bs\_quotation\_versions`



Preserves quotation history and accepted terms.



Proposed fields:



\- `id`: Primary key.

\- `quotation\_id`: Parent quotation.

\- `version\_number`: Sequential version.

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



An accepted quotation version must remain unchanged. Revisions must create a new version.



\### 3.5 Quotation Items: `bs\_quotation\_items`



Stores itinerary components and itemised quotation costs.



Proposed fields:



\- `id`: Primary key.

\- `quotation\_version\_id`: Associated quotation version.

\- `item\_type`: Accommodation, transport, activity, guide, meal, or other.

\- `day\_number`: Relevant itinerary day.

\- `title`: Item title.

\- `description`: Item description.

\- `quantity`: Item quantity.

\- `unit\_amount`: Cost per unit.

\- `total\_amount`: Item total.

\- `sort\_order`: Display sequence.



Quotation items are tied to a specific quotation version. Once a quotation version has been sent to a customer, its items must not be edited or deleted. Any changes to the itinerary, descriptions, quantities, or costs must be made in a new quotation version. This preserves the exact details of previously sent and accepted quotations.



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

\- `status`: Booking status.

\- `confirmed\_at`: Confirmation timestamp.

\- `created\_at`: Creation timestamp.

\- `updated\_at`: Last modification timestamp.



A booking is not automatically confirmed when a reservation request is submitted.



\### 3.7 Payments: `bs\_payments`



Stores manually recorded payments.



Proposed fields:



\- `id`: Primary key.

\- `booking\_id`: Associated booking.

\- `amount`: Amount received.

\- `currency`: Payment currency.

\- `payment\_method`: Bank transfer, cash, or another approved external method.

\- `payment\_date`: Date received.

\- `reference`: Optional transaction reference.

\- `verification\_status`: Pending verification, verified, or rejected.

\- `verified\_by`: Administrator who verified the payment.

\- `verified\_at`: Verification timestamp.

\- `created\_at`: Record creation timestamp.



* Payment records must be retained for audit purposes. Rejected payments must not be treated as received funds. Corrections to payment records must preserve an activity history rather than silently overwriting or deleting the original record.
* No card numbers, banking credentials, or online payment information will be stored.



\### 3.8 Documents: `bs\_documents`



Stores references to generated business documents.



Proposed fields:



\- `id`: Primary key.

\- `customer\_id`: Associated customer.

\- `quotation\_id`: Optional quotation reference.

\- `booking\_id`: Optional booking reference.

\- `document\_type`: Quotation, invoice, booking confirmation, receipt, or other.

\- `file\_reference`: Protected file reference.

\- `created\_at`: Creation timestamp.



Private documents must not be exposed through publicly accessible upload URLs.



Document storage and access rules:



\- Store private documents outside the publicly accessible uploads directory, or use an equivalent protected storage mechanism.

\- Serve documents only through authenticated or securely authorized download endpoints.

\- Guest access must use secure, random, expiring, and revocable access tokens.

\- Verify the user's permissions or access token before serving a document.

\- Do not expose physical server paths or predictable file locations.

\- Expired or revoked access links must no longer work.



\### 3.9 Activity Logs: `bs\_activity\_log`



Records important administrative and business actions.



Proposed fields:



\- `id`: Primary key.

\- `user\_id`: WordPress administrator ID.

\- `entity\_type`: Type of affected record.

\- `entity\_id`: Affected record ID.

\- `action`: Action performed.

\- `description`: Brief activity description.

\- `created\_at`: Event timestamp.



Sensitive information, passwords, access tokens, and payment credentials must never be written to logs.



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

\- Activity logs can reference different business records.



\### Relationship and Deletion Policies



The implementation must follow these rules:



\- Do not use cascading deletion for financial or booking records.

\- Prevent deletion of customers who have associated bookings or financial records.

\- Preserve accepted quotations, quotation versions, bookings, and payment history.

\- Use soft deletion or anonymization where appropriate instead of permanently deleting business records.

\- Restrict deletion of records referenced by other business records.

\- Customer and enquiry retention and deletion must follow the approved data retention policy.

\- Deleting a WordPress administrator account must not automatically delete business activity history.



\## 5. Data Integrity



The implementation must:



\- Use primary keys for all custom tables.

\- Use appropriate foreign-key relationships.

\- Add indexes to frequently queried fields.

\- Enforce unique quotation and booking reference numbers.

\- Preserve historical quotation versions.

\- Prevent invalid booking and payment state transitions.

\- Use database transactions for operations involving multiple related records.

\- Use UTC timestamps internally and display dates in the configured Bhutan timezone.



\## 6. Privacy and Security



\- Restrict private records to authorized administrators.

\- Use WordPress capabilities to control access.

\- Validate, sanitize, and escape all user input and output.

\- Use prepared database queries.

\- Protect administrative operations with nonce verification.

\- Do not expose private records through public REST endpoints.

\- Generate customer access tokens using a cryptographically secure random generator.

\- Store only token hashes in the database, never the original usable tokens.

\- Enforce token expiry and revocation.

\- Validate the token and its permissions before granting access.

\- Limit tokens to their intended customer, quotation, booking, or document.

\- Store only necessary customer information.- Establish data retention and deletion procedures.

\- Protect generated documents with access-controlled storage.

\- Maintain secure backups.



\## 7. Currency Handling



The system must support multiple quotation currencies.



\- BTN will be the default internal business currency.

\- USD, EUR, INR, and other required currencies can be supported.

\- Store monetary values using appropriate fixed-precision decimal fields.

\- Preserve the currency and exchange-rate information applicable when a quotation is issued.

\- Do not recalculate historical quotations using current exchange rates.

\- Record payments in the currency actually received.



\## 8. Database Naming Convention



Custom table names will follow:



`{wordpress\_prefix}bs\_{table\_name}`



Examples:



\- `wp\_bs\_customers`

\- `wp\_bs\_enquiries`

\- `wp\_bs\_quotations`

\- `wp\_bs\_bookings`

\- `wp\_bs\_payments`



The actual WordPress prefix will be determined dynamically during plugin installation.



\## 9. Implementation Considerations



The database will be created and maintained through the Blue Sheep Business Manager plugin.



The plugin must include:



\- Database installation routines.

\- Schema version tracking.

\- Controlled database migrations.

\- Index creation and validation.

\- Safe upgrade procedures.

\- Appropriate foreign-key handling.

\- Backup and restoration considerations.

\- Preserve existing business records during schema upgrades.

\- Use database transactions for migrations where supported.

\- Record migration results and errors without logging sensitive information.

\- Provide a recovery procedure for failed migrations.

\- Verify database integrity after installation and upgrades.



Database changes must be tested locally before deployment.



\## 10. Outstanding Design Decisions



The following details must be finalized before implementation:



\- Exact status values and permitted transitions.

\- Data retention periods.

\- Quotation reference format.

\- Booking reference format.

\- Customer document storage strategy.

\- Foreign-key and deletion policies.

\- Currency precision and exchange-rate storage.

\- Notification and activity-log retention.



\## 11. Architecture Status



The custom database table approach has been approved as the architectural direction.



This document is the proposed database design and requires review before the schema is implemented.

