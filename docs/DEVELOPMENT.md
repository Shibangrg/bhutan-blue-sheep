\# Development Guide



\## 1. Project Overview



This project is a complete redevelopment of the Bhutan Blue Sheep Tours \& Travels website using WordPress.



The development approach prioritizes security, maintainability, performance, and ease of use for both website administrators.



\## 2. Development Environment



| Tool | Purpose |

|---|---|

| Windows | Local development operating system |

| LocalWP | Local WordPress environment |

| Visual Studio Code | Code editor |

| Git | Version control |

| GitHub | Remote repository and source code backup |

| PowerShell | Command-line operations |

| WordPress | CMS and application foundation |



The project workspace is located at:



`D:\\Bhutan-Blue-Sheep\\website`



\## 3. Development Principles



\- Develop and test locally before production deployment.

\- Never make unverified changes to the live website.

\- Keep business logic separate from presentation.

\- Implement custom functionality through a dedicated WordPress plugin.

\- Avoid unnecessary third-party plugins and dependencies.

\- Follow WordPress coding and security standards.

\- Do not store sensitive customer or payment credentials.

\- Keep production data, secrets, and backups outside the Git repository.



\## 4. Project Structure



The planned repository structure is:



```text

website/

├── .gitignore

├── README.md

├── docs/

│   ├── ARCHITECTURE.md

│   └── DEVELOPMENT.md

├── theme/

│   └── blue-sheep-theme/

└── plugins/

&#x20;   └── blue-sheep-manager/

```



The theme will manage the public website's presentation.



The Blue Sheep Business Manager plugin will manage business functionality, including enquiries, quotations, bookings, customer records, and manual payment records.



\## 5. Git Workflow



Use Git to track all project changes.



Recommended workflow:



1\. Make a focused change.

2\. Review modified files.

3\. Test the change locally.

4\. Check the Git status.

5\. Commit the verified changes with a descriptive message.



Example:



```powershell

git status

git add .

git commit -m "feat: add initial website theme structure"

```



Avoid committing credentials, database exports, customer records, or production backups.



\## 6. Development and Testing



\- Build features incrementally.

\- Test each feature in the local WordPress environment.

\- Verify administrator permissions.

\- Validate user inputs and protect sensitive operations.

\- Test responsive layouts on desktop, tablet, and mobile.

\- Document important architectural and implementation decisions.



\## 7. Deployment Principles



Production deployment will only begin after:



\- Hosting access is confirmed.

\- A verified production backup exists.

\- A staging environment is available.

\- Compatibility and security checks are completed.

\- Deployment and rollback procedures are documented.



No production changes should be made during initial local development.



\## 8. Security Requirements



\- Apply WordPress authentication and capability checks.

\- Validate, sanitize, and escape data appropriately.

\- Use nonces for protected administrative actions.

\- Use prepared database queries.

\- Protect private customer and booking records.

\- Use HTTPS in production.

\- Keep credentials outside version control.

\- Maintain secure, independently stored backups.



Online payment gateways and payment portals are excluded from this project.



\## 9. Development Status



The project is currently in Phase 4: Technical Architecture and Setup.



Development of the public website and business-management plugin will begin after the technical setup and design decisions are finalized.



