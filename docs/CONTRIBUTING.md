# **Contributing to the Unicef Bebbo CMS**

Thank you for your interest in contributing to Unicef Bebbo CMS! We aim
to build an inclusive, accessible, and impactful content management
system to provide parents and caregivers with valuable, localized, and
evidence-based information. This document explains how to contribute
changes to the Unicef Bebbo CMS repository.

Feel free to browse the [open
issues](https://github.com/UNICEFECAR/parenting-app-bebbo-CMS/issues?q=is%3Aissue+is%3Aopen+)
and file new ones, all feedback is welcome!

We welcome submissions and appreciate your contributions.

This guide is broken up into the following sections. It is recommended
that you follow these steps in order:

- Code of conduct - our code of conduct ensures a respectful and inclusive community for everyone.
- How to contribute - steps to get started with contributing to the project.
- Setting up your environment - detailed instructions on setting up your environment,
  making changes, and submitting contributions to the repository.
- Style guide - learn about our coding standards and best practices to keep
  the codebase clean and consistent.
- Pull request checklist - helpful tips and guidelines to make sure your
  pull request gets reviewed and merged quickly.
- Reporting an issue - find out how to submit bug reports or feature requests,
  so they're addressed effectively.
- Review process and approval workflow
- Contributed modules
- Custom libraries
- Custom modules
- Theme
- Custom roles
- Menus
- Configurations.

## **Code of conduct**

Please make sure to read and observe the [code of conduct](CODE_OF_CONDUCT.md).

## **How to contribute**

If you would like to contribute to the CMS, start by searching through
our [open issues](https://github.com/UNICEFECAR/parenting-app-bebbo-CMS/issues?q=is%3Aissue+is%3Aopen+)
or [pull requests](https://github.com/UNICEFECAR/parenting-app-bebbo-CMS/pulls)
to see if someone else has raised a similar idea or question.

If you don't see your idea listed and you think it fits into the goal of
the project, you can raise an issue and the maintainers will check it
out.

## **Setting up your environment**

Follow the steps mentioned [here](../README.md#installation).

Feel free to reach out if you have any questions or need assistance during the setup process!

## **Style guide**

To ensure our codebase remains clean, consistent, and easy to maintain,
we follow specific coding standards and best practices. Our style guide
covers important topics such as naming conventions, code formatting, and
documentation standards. Following these guidelines will help facilitate
collaboration and improve the overall quality of our contributions.

For detailed information on our coding standards and practices, please
refer to the [coding style guide](CODING_STYLE_GUIDE.md).

## **Pull request checklist**

Below are extensive steps you can follow to easily open a pull request.

- Be sure to follow our coding standards outlined in the [coding style guide](CODING_STYLE_GUIDE.md).

- Create and switch to a new branch from the dev branch. Use the following command: `git checkout -b feature_name`

- Implement the changes you want to contribute.

  - Install and run a PHPCS to check the coding standards locally.

- After making your changes, stage them using: `git add .`

- Then commit with a clear and concise message: `git commit -m "A brief description of your changes"`

- Push your new branch to your forked repository: `git push origin your-branch-name`

- To open a pull request, go to the original repository on GitHub. You will see a notification for your recently pushed branch.
  Click on "Compare & pull request."

- Create a pull request (PR) against the \"dev\" branch of the original repository.
  The title of your PR should describe your changes, for example, \"Adding new distribution to the application.\"
  In the PR description, provide additional information about the changes, and their purpose.

- Wait for the project maintainers to review your pull request.
  They may provide feedback or request further changes. If your pull request is not accepted,
  the reviewer will mention the reason in the PR comments.

You can learn more about pull requests and how they work by checking out [Github's documentation](https://docs.github.com/en/pull-requests/collaborating-with-pull-requests/proposing-changes-to-your-work-with-pull-requests/about-pull-requests).

## **Reporting an issue**

We value your feedback and encourage you to report any issues you
encounter or suggest new features. Reporting issues helps us identify
areas for improvement and ensures the project remains accessible,
efficient, and impactful.

**How to Report an Issue:**

1. **Search Existing Issues:** Before creating a new issue, check the issues section of the repository to see if the problem has already been reported. If a similar issue exists, feel free to add comments or additional details.

2. **Create a New Issue**

    - Go to the Issues tab in the repository.

    - Click on `New Issue`.

    - Choose the appropriate issue template (e.g., \"Bug Report\" or \"Feature Request\") if available.

3. **Provide a Detailed Description**

    Include the following details in your issue

    - **Summary:** A concise title that clearly describes the issue or feature.

    - **Expected Behavior:** Highlight the behavior that was supposed to happen.

    - **Actual Behavior:** Highlight what actually occurred.

    - **Steps to Reproduce (for bugs):** A step-by-step explanation to help us replicate the issue.

    - **Environment:** Mention the browser, OS, or other relevant environment details where the issue occurs.

    - **Screenshots or Logs:** If applicable, attach screenshots or error logs to provide additional context.

4. **Label the Issue (Optional)**

    Add appropriate labels like \"bug,\" \"enhancement,\" or \"design\" to categorize the issue.
    Otherwise, maintainers will label it during triage.

5.  **Stay Engaged:** Be responsive to follow-up questions or requests for additional information from maintainers.

## **Code review and approval process**

To ensure the quality and consistency of contributions to Unicef Bebbo
CMS, all submissions go through a structured review and approval
process. This section outlines what to expect after you've created a
pull request (PR).

#### **1. Automated Checks**

- Once you submit your PR, our continuous integration (CI) setup will automatically run checks, including:

  - Code linting for style compliance.

  - Security checks are automated using CodeQL to ensure no vulnerabilities are introduced.

- If any checks fail, review the logs, update your code, and push changes to the same branch to rerun the checks.

#### **2. Code Review**

- A maintainer or reviewer will evaluate your pull request. They will focus on:

  - Adherence to coding standards and the style guide.

  - Functionality and performance of your changes.

  - Clarity and quality of documentation.

  - Compatibility with the existing codebase.

- Expect comments or suggestions for improvement. Feedback will be provided constructively to help refine your contribution.

#### **3. Revisions if required**

- Address any requested changes by updating your branch and pushing the changes.

- Add a comment on Github to explain how you resolved each piece of feedback to help reviewers re-evaluate efficiently.

#### **4. Approval**

- Once your PR meets all requirements and receives approval from at least one maintainer:

  - It will be marked as \"Ready to Merge.\"

  - If multiple maintainers' approval is required, additional reviewers will be assigned.

#### **5. Merging**

- Approved pull requests are merged into the repository's main branch by a maintainer.

- The PR will be closed, and you'll be notified when the changes are live.

#### **6. Post-Merge Workflow**

- After your PR is merged:

  - The changes will be included in the next scheduled release.

  - Your contribution should be acknowledged in the release notes, if applicable.

- Feel free to monitor the live application and report any issues or suggest improvements for subsequent contributions.

#### **Tips for a Smooth Review Process**

- Write clear, concise commit messages and PR descriptions.

- Ensure your changes are thoroughly tested and follow the style guide.

- Be proactive in responding to feedback and questions from maintainers.

## **Custom libraries**

- CKEDITOR

## **Custom modules**

The following custom modules are installed as part of the profile:

- custom_serialization

- group_country_field

- pb_custom_field

- pb_custom_form

- pb_custom_migrate

- pb_custom_rest_api

- pb_custom_standard_deviation

## **Theme**

The following themes are installed and enabled by the profile:

- claro

- tara

## **Custom roles**

- **Globaladmin**: Manages all countries, configures languages, and offloads countries.

- **Senior editor**: Access to create, update, publish, and translate content.

- **SME**: Access to update and approve content.

- **Editor**: Access to create, update, and translate content.

- **Country admin**: Manages country users and language content.

Each user role has a dashboard. Country admin and senior editor have
access to country reports.

## **Menus**

- **Global content list**: Shows all published content.

- **Country content list**: Shows language-specific content for the user.

- **Add content**: Editors, Global Admin, and Senior Editors can create content.

- **Manage Taxonomies**: Shows all taxonomy terms.

- **Manage Media**: Allows users to manage image-related details.

- **Manage Country**: Global Admin can add or update country and user details.

- **Manage Language**: Create or update languages.

- **Manage Users**: Global Admin can add new admins and assign languages.

- **Manage Translation**: Users can send content translation requests.

- **Google Analytics**: Global Admin can add an analytics ID.

- **Import Taxonomy**: Import taxonomy term values.

- **Manage reports**: View reports by allowed language.

## **Configurations**

The installation profile assists in setting up a base instance.
