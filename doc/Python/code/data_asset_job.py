import os
import time

from jobseeker import JobSeeker


ENVIRONMENT = os.getenv("JOBSEEKER_ENVIRONMENT", "LOCAL")
JOB_NAME = os.getenv("JOB_NAME", "customer-asset-example")


def summarize_active_customers(customers):
    """Return country totals and the number of active rows processed."""

    active_count = 0
    by_country = {}
    for customer in customers:
        if str(customer.get("active", "")).lower() not in ("1", "true", "yes"):
            continue
        active_count += 1
        country = customer.get("country") or "Unknown"
        by_country[country] = by_country.get(country, 0) + 1

    summary = [
        {"country": country, "active_customers": count}
        for country, count in sorted(by_country.items())
    ]
    return summary, active_count


def operation():
    started_at = time.monotonic()

    with JobSeeker(environment=ENVIRONMENT, job=JOB_NAME) as js:
        source = js.asset("customer-reference")
        target = js.asset("customer-summary", mode="output")
        assert source is not None and target is not None

        with js.task("Summarize customer reference", "Data Assets") as tmf:
            customers = source.read()
            summary, active_count = summarize_active_customers(customers)
            target.write(summary)
            tmf.finish(total=len(customers), processed=active_count, msg="Customer asset summarized")
            js.email_metrics(
                dataset=source.uri,
                rows_read=len(customers),
                rows_written=len(summary),
                rows_rejected=len(customers) - active_count,
                duration="{:.2f} seconds".format(time.monotonic() - started_at),
            )


if __name__ == "__main__":
    operation()
