# Unicef Bebbo CMS Coding Style Guide

This document explains the coding standards used within the Unicef Bebbo CMS repository. Unicef Bebbo CMS primarily follows the [Drupal coding standards](https://www.drupal.org/docs/develop/standards), supplemented with additional guidelines. Following these standards will help maintain code quality and consistency across the project making it easier for codes to be reviewed and integrated.  

While all the rules are not absolute, following them will help us streamline the review process and have a clear and maintainable codebase. You can take a few moments to familiarise yourself with the guidelines, which will help make your contributions more impactful.  

Below you'll find our guidelines and since they aren't rules, you can use your best judgement where appropriate while keeping clarity and readability in mind.

## General Style

This project follows the Drupal coding guidelines for PHP, CSS and JavaScript. Drupal provides clear standards for:

1. **Php**: Follow the [Drupal PHP coding standards](https://www.drupal.org/docs/develop/standards/php), especially for indentation, function naming and file structure.
2. **CSS**: Ensure that styles follow the [Drupal CSS coding standards](https://www.drupal.org/docs/develop/standards/css), which emphasize selector organization and specificity.
3. **JavaScript**: Refer to the [Drupal JavaScript coding standards](https://www.drupal.org/docs/develop/standards/javascript-coding-standards) for custom JavaScript in Drupal.

## Additional Project-Specific Guidelines

- **Readability first**: Avoid anything that hinders readability. Your code should be understandable to new contributors without too many notes or explanations.
- **Modular functions**: Keep functions small and focused on a single responsibility. Refactor lengthy or complex functions as needed.
- **Error handling**: Use clear, concise error messages with relevant details. Avoid generic error messages that don’t provide insight into the issue.

## Code Structure and Formatting

### File Organization

Structure code in a way that groups related functionality. Adhere to Drupal’s conventions on directory structure and module organization as specified in [Drupal's structure guidelines](https://www.drupal.org/docs/understanding-drupal/directory-structure).

### Indentation and Line Length

- **Indentation**: Use 2 spaces with no tab for indentation, in line with Drupal standards.
- **Line length**: Limit lines to 80 characters for PHP and CSS, and 100 characters for JavaScript.

### Naming Conventions

- Use **snake_case** for function and variable names, as per Drupal's naming conventions.
- Classes should be in **PascalCase**.
- Properties should always use **camelCase**, even if the rest of the file uses snake_case for variables.

### Comments

- **Descriptive comments**: Comments should explain `why` something is done, not just `what` it does. Each function or complex block of code should have a brief comment if it requires an explanation beyond code readability.
- **API documentation**: For functions exposed to other modules or the public API, use [PHPDoc](https://docs.phpdoc.org/guide/references/phpdoc/tags/api.html) for descriptions, parameters, and expected return types.
- **TODOs and FIXMEs**: Use TODO and FIXME tags for incomplete or problematic areas in code, following Drupal’s conventions for documenting future work.

## Testing and Validation

Testing is critical to maintain the quality and stability of the Unicef Bebbo CMS codebase. In order to maintain this, all contributors must follow the guidelines below.

- **Write tests for every module**: Every new module should include Behat tests covering core functionality, in line with [Drupal testing standards](https://www.drupal.org/docs/7/automated-testing-for-drupal-7).
- **Descriptive test names**: Use meaningful test names that describe what is being tested and the expected outcome.
- **Error cases**: Include tests for edge cases and possible errors to confirm that the code handles them as expected.

## API Design and Interfaces

- **Keep APIs simple and intuitive**: Design public APIs for ease of use and clarity. Avoid creating deeply nested interfaces unless necessary.
- **Reduce complexity**: Aim to hide internal details and expose only what is necessary, in keeping with Drupal’s API design principles.
- **Document assumptions**: Clearly explain any assumptions or unexpected behaviour in your documentation or comments.

## Error Handling and Exceptions

- **Clear error messages**: Use specific error messages, as vague messages can hinder troubleshooting.
- **Custom exceptions**: When necessary, create custom exception classes to handle specific errors.

## Security and Privacy

Unicef Bebbo is a UNICEF initiative for parents with young children, so security and privacy are paramount.

- **Data privacy**: Follow Drupal’s security guidelines, ensuring personal data is encrypted when required.
- **Validate inputs**: Validate and sanitize inputs to prevent XSS, SQL injection, and other attacks.
- **Reduce data exposure**: Only expose the minimal amount of data necessary.

## Code Reviews and Pull Requests

- **Prepare code for review**: Ensure that your code is well-documented, passes all tests, and adheres to the style guide before submitting a pull request.
- **Meaningful commit messages**: Use concise commit messages, describing the purpose of the change. For example, “Fix bug with data validation in user form” rather than “Bug fix.”
- **Respond to feedback constructively**: Code reviews are collaborative, so respond thoughtfully to feedback.

## Branch Naming and Commit Message Guidelines

### **Branch Naming**
When naming branches, use the following format based on the type of work:

- **Bugs**: `bug/{branch_name}`  
  For branches fixing bugs or resolving issues.  
  - Example: `bug/fix-login-error`
- **Hotfixes**: `hotfix/{branch_name}`  
  For urgent fixes that need to be deployed immediately.  
  - Example: `hotfix/critical-security-patch`
- **Features**: `feature/{branch_name}`  
  For new features or enhancements.  
  - Example: `feature/add-user-profile-page`

### Commit Message: 
When committing changes to any of the Git submodules, follow these guidelines for commit messages:

#### Prefixes: 
Use one of the following prefixes to indicate the type of change:

- **Create**: `[commit message]` – For creating a new component.  
  Example: `Create: user authentication module`
- **Add**: `[commit message]` – For additions to an existing component.  
  Example: `Add: email validation to login form`
- **Fix**: `[commit message]` – For fixing a bug in an existing component.  
  Example: `Fix: issue with session persistence during login`
- **Refactor**: `[commit message]` – For refactoring an existing component.  
  Example: `Refactor: API service for improved performance`

## Accessibility

Bebbo prioritizes accessibility to ensure inclusivity for all users, including individuals with disabilities. Contributors must follow [Drupal Accessibility Standards](https://www.drupal.org/about/features/accessibility) and adhere to WCAG 2.1 AA guidelines.

Key points to note:

- Use semantic HTML and ARIA roles appropriately.
- Ensure all content is perceivable, operable, and understandable.
- Test accessibility using automated tools and assistive technologies (e.g., screen readers).

For more information, see [Drupal's Accessibility Handbook](https://www.drupal.org/docs/accessibility/drupal-accessibility-features).

By following these guidelines, you’re helping Unicef Bebbo stay reliable, secure, and easy to maintain while making a real difference for the families who rely on us. Also keep in mind that consistency, clarity, and collaboration are key to everything we do. 

Thank you for your dedication to keeping Unicef Bebbo a safe, impactful platform that truly supports parents and children.
