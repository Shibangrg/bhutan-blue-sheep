\# Bhutan Blue Sheep Tours \& Travels

\## Technical Architecture



\*\*Project:\*\* Bhutan Blue Sheep Tours \& Travels Website  

\*\*Document:\*\* Technical Architecture  

\*\*Status:\*\* Proposed baseline  

\*\*Version:\*\* 1.0  

\*\*Last Updated:\*\* 9 November 2026



\---



\## 1. Project Overview



The project involves developing a new WordPress-based website for Bhutan Blue Sheep Tours \& Travels.



The system will provide two primary interfaces:



1\. \*\*Public Website:\*\* Allows visitors to explore Bhutan, discover tour packages and experiences, read travel articles, and submit travel enquiries or reservation requests.

2\. \*\*Business Management Dashboard:\*\* Allows authorized administrators to manage website content, customers, enquiries, itineraries, quotations, bookings, payment records, and operational notifications.



The system will prioritize security, data minimization, maintainability, responsive design, and ease of administration.



\## 2. Architecture Overview



The application will use a modular WordPress architecture.



\### 2.1 Core Components



| Component | Responsibility |

|---|---|

| WordPress Core | CMS foundation, authentication, user management, administration |

| Custom Theme | Public website presentation and responsive layouts |

| Blue Sheep Business Manager | Business workflows, dashboard modules, booking operations and business logic |

| WordPress Database | Website content and application data |

| WordPress REST API | Controlled communication between application components where required |

| Email Service | Transactional notifications and customer communication |

| Local Development Environment | Development and testing before production deployment |

| Git | Source control and change tracking |



\### 2.2 High-Level Architecture



Visitor or Administrator  

↓  

WordPress Application  

↓  

Custom Theme / Business Manager Plugin  

↓  

WordPress APIs and Application Services  

↓  

WordPress Database



External integrations:



\- Transactional email provider

\- WhatsApp and telephone communication

\- External bank transfer or cash payment arrangements



Online payment gateways and payment portals are explicitly excluded.



\## 3. Public Website Architecture



The public website will be implemented through a custom WordPress theme.



\### 3.1 Public Content Types



The following content types will be supported:



| Content Type | Purpose |

|---|---|

| Tours | Tour packages and itineraries |

| Destinations | Bhutanese destinations and locations |

| Experiences | Cultural, adventure, spiritual and nature-based activities |

| Travel Journal | Travel guides, stories and practical information |

| Testimonials | Approved customer reviews |



The WordPress Media Library will manage photographs and other public media.



\### 3.2 Content Relationships



Content should be interconnected where relevant.



Examples:



\- Destinations can reference related tours.

\- Tours can reference multiple destinations.

\- Experiences can reference related destinations and tours.

\- Travel Journal articles can reference destinations and tours.

\- Testimonials can be displayed on relevant public pages.



These relationships will be implemented using suitable custom fields and taxonomies.



\### 3.3 Theme Responsibilities



The custom theme will handle:



\- Website layout and navigation.

\- Homepage and landing pages.

\- Tour and destination listings.

\- Individual content templates.

\- Responsive layouts.

\- Typography, colors and visual identity.

\- Accessibility and frontend presentation.



The theme must not contain critical business operations or booking-processing logic.



\## 4. Business Management Architecture



The business functionality will be implemented through a separate custom WordPress plugin named \*\*Blue Sheep Business Manager\*\*.



\### 4.1 Plugin Responsibilities



The plugin will manage:



\- Business dashboard.

\- Customer records.

\- Custom trip enquiries.

\- Fixed package reservation requests.

\- Itinerary creation.

\- Quotation preparation and revisions.

\- Quotation acceptance and modification requests.

\- Booking management.

\- Manual payment records.

\- PDF document generation.

\- Transactional email notifications.

\- Administrative activity logs.



\### 4.2 Business Workflow



1\. Customer submits a trip enquiry or package reservation request.

2\. The system records the request and notifies administrators.

3\. An administrator reviews the request.

4\. The administrator prepares an itinerary and quotation.

5\. The quotation is shared through a secure customer link.

6\. The customer accepts, declines or requests modifications.

7\. Payment is arranged externally through bank transfer or cash.

8\. An administrator verifies the payment outside the website and records the result.

9\. The administrator confirms the booking according to the agreed payment requirements.

10\. The customer receives the relevant booking documents and notifications.



A reservation request must never automatically become a confirmed booking.



\## 5. Database Architecture



The system will use the existing WordPress database infrastructure.



\### 5.1 WordPress-Managed Data



WordPress will manage:



\- Public content.

\- Media attachments.

\- User accounts.

\- Roles and capabilities.

\- Standard taxonomies and metadata.



\### 5.2 Private Business Data



Operational records will use protected application data structures. Custom database tables are the preferred baseline for transactional records.



Proposed tables:



| Table | Purpose |

|---|---|

| `bs\_customers` | Customer contact and profile information |

| `bs\_enquiries` | Custom trip enquiry records |

| `bs\_bookings` | Reservation and confirmed booking records |

| `bs\_booking\_items` | Itinerary and booking details |

| `bs\_payments` | Manually recorded payment information |

| `bs\_activity\_log` | Important administrative and business events |



Actual table definitions, relationships, indexes and migration strategy will be documented during detailed database design.



All custom tables must use the active WordPress database prefix.



\### 5.3 Data Separation



Private customer and booking information must never be exposed through public WordPress queries or unrestricted REST API endpoints.



Public content and private operational data must have separate access controls.



\## 6. User and Access Architecture



Two authorized administrators will manage the system:



\- Business owner.

\- Technical administrator.



Each administrator will have an individual WordPress account with the required full administrative permissions.



Security requirements:



\- Separate user accounts.

\- Strong passwords.

\- Multi-factor authentication where supported.

\- Capability-based authorization.

\- Secure administrative sessions.

\- Activity logging for important operations.



Customer accounts are not required.



Customers will access quotations and booking documents through secure, expiring and revocable links.



\## 7. API Architecture



The WordPress REST API will be used only where required.



Potential uses include:



\- Dynamic frontend interactions.

\- Enquiry submission.

\- Controlled retrieval of public content.

\- Dashboard operations where asynchronous requests improve usability.



All private API endpoints must enforce authentication or secure token validation, capability checks, input validation and appropriate permission controls.



No private customer or booking information should be available through unauthenticated public endpoints.



\## 8. Email and External Integrations



The application will support transactional emails for:



\- Enquiry acknowledgements.

\- Reservation acknowledgements.

\- Quotation delivery and updates.

\- Quotation acceptance.

\- Modification requests.

\- Payment verification notifications.

\- Booking confirmation.

\- Pre-departure information.

\- Payment reminders.

\- Cancellation notifications.



Email delivery will use an authenticated SMTP service or suitable transactional email provider.



WhatsApp, telephone and email will remain the primary communication channels.



Online payment processing is outside the project scope.



\## 9. Security Architecture



Security will be incorporated into each application layer.



Key requirements:



\- HTTPS for production.

\- Secure authentication and authorization.

\- WordPress nonces for appropriate request verification.

\- Input validation and sanitization.

\- Output escaping.

\- Prepared database queries.

\- Restricted REST API access.

\- Secure, expiring customer access tokens.

\- Protection against unauthorized file access.

\- Regular backups stored separately from the production server.

\- Secure configuration and secret management.

\- Audit logging without recording credentials or sensitive secrets.

\- Data minimization and retention controls.



The website must not collect or store card details, banking credentials, passport scans or unnecessary identity documents.



\## 10. Development and Deployment Architecture



Development will take place locally before deployment.



\### Development tools



\- LocalWP for the local WordPress environment.

\- Visual Studio Code for development.

\- Git for version control.

\- GitHub or another approved remote repository for source backup and collaboration.

\- Browser developer tools for debugging and frontend testing.



\### Environment separation



The project will maintain separate environments:



1\. Local development.

2\. Staging.

3\. Production.



Production changes must not be performed without a verified backup and an appropriate deployment procedure.



Production customer data, credentials, backup archives and private configuration files must not be committed to Git.



\## 11. Performance, Accessibility and SEO



The application should:



\- Support desktop, tablet and mobile devices.

\- Use responsive layouts.

\- Optimize images and media delivery.

\- Minimize unnecessary plugins and scripts.

\- Provide accessible navigation and forms.

\- Use semantic HTML.

\- Support search-engine-friendly URLs.

\- Include appropriate metadata and structured content.

\- Maintain acceptable page loading performance.



\## 12. Architecture Decisions



| Decision | Selected Approach |

|---|---|

| CMS | WordPress |

| Frontend | Custom WordPress theme |

| Business logic | Separate custom plugin |

| Database | WordPress database with protected custom tables where appropriate |

| API | WordPress REST API when needed |

| Customer authentication | Guest-first access |

| Administrative access | Two individual full-access accounts |

| Payments | External bank transfer or cash |

| Customer communication | Email, WhatsApp and telephone |

| Development | LocalWP |

| Source control | Git |

| Deployment | Local → Staging → Production |



\## 13. Future Technical Design



The following areas require detailed design before implementation:



\- Final theme folder structure.

\- Plugin module structure.

\- Database schema and relationships.

\- WordPress capabilities and permission model.

\- Secure quotation and document access.

\- Email delivery and reminder configuration.

\- Backup and recovery procedures.

\- Staging and production deployment.

\- Data retention and deletion procedures.



\---



\*\*Architecture status:\*\* Proposed baseline, pending technical review and approval.

