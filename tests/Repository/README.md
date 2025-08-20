# Repository Tests

This directory contains unit tests for the repository classes in the `src/Repository` directory. Each test ensures that the repository methods behave as expected under various conditions.

## Test Coverage

- **AdminPasswordRepositoryTest**: Tests for `findFirst` method.
- **CsvFieldConfigRepositoryTest**: Tests for `getCurrentConfig` and `saveConfig` methods.
- **SMTPConfigRepositoryTest**: Tests for `getConfig` method.
- **EmailSentRepositoryTest**: Tests for `findRecentEmails` and other methods.
- **UserRepositoryTest**: Tests for `findByUsername` and other methods.

## Running Tests

To execute the tests, use the following command:

```bash
make test
```

Ensure that the Docker environment is running and the database is properly configured for testing.
