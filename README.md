# German Company Lookup and VAT Checker

## German Company Lookup

### 1. Why one must not scrape from handelregister.de

**Reason:**

- **a. Strict and heavy penalites:** it uses cloudflare to check for abuse, and scraping will make German govt approach you with with penalties. Germans are already very strict with IP laws, they take action when you pirate/ violate terms. So, getting your IP blacklisted and getting the German civil/legal/criminal enforcement against your organization is the last thing you want to do.
- **b.** Their website already doesnt load in my Indian IP address.
- **c.** Strict 60 requests per hour rate limit is already enforced on the platform.

### 2. What is LEI and which companies will not have one?

**Answer:** legal entity identifier. A primary key in GLEIF's shared database with Local operating units. It is the identification number of a business that tells its identity and the parent company's identity. Typically small businesses, sole proprieterships do not have such a number. But they still can apply for it themselves, with the overhead of ~3K inr/YR.

**Fun fact:** OnDemand has a VAT number, it does not have an LEI number because LEI is a requirement for orgs partaking in financial markets. So, when the requirement arises, we can have it.

### 3. A user types "Mueller", but the company is "Müller GmbH". How does your search handle it?

First, the search tries for MUELLER, if the results are less than 10, the UE is converted to ü and search is done again.

## VAT Checker

### 4. What does a "valid" result prove, and what does it not prove?

Valid means that the company has registered with the EU for transactions within the EU . But what remains unconfirmed is the businesses' legitimacy/ activity. Only confirmed is the authority.

### 5. Why check the format before calling VIES?

To avoid unnecessary API requests and stay within the 60 requests per minute limit.

### 6. Why might a valid German VAT number come back with no company name or address?

Privacy Issues. And also, it is optional to expose your address because youre shielded by data-protection and information-disclosure rules.
