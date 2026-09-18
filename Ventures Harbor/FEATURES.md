# Ventures Harbor – Feature Explanation

## 1. Quit Option After The Partner Meeting

- Users can quit the venture only after the partner meeting has taken place. The meeting may be
  held in person or online — see §2 — and either one opens the window.
- Once the meeting is marked as completed, a 24-hour exit window starts.
- If a user quits within those 24 hours, the platform will process the exit according to the platform policy.
- After 24 hours, the normal quit option is no longer available.

## 1b. Refund Requests — two separate types

Refund requests are split into two flows, shown and filtered separately on both the user side and
the admin panel.

**A. Refund for Account Deletion**
- Raised when a user deletes their account.
- The form asks for **bank account details only** (holder name, account number, IFSC, bank name).
  No exit reason, no meeting details.
- The claimed amount is every commitment fee the user has paid, minus anything already refunded or
  already pending. The admin decides the final amount.

**B. Refund for Exit**
- **Before the meeting** — nothing is charged until an active applicant pays at
  confirmation, so pulling out at this stage is a plain *withdrawal*, not a refund. A `pending` or
  `selected` applicant gets a "Remove request" / "Decline & withdraw" button; no fee, no refund
  request, and they may apply again later.
- **After the meeting** — a member who exits within the 24-hour window gets a **full
  refund of the commitment fee**, collected against the same bank-details form.
- **Exit Without Refund** — available before the meeting and after the window has closed. The member
  leaves knowing the commitment fee is forfeited. No bank details are collected and nothing enters
  the payout queue; the departure is recorded as a `waived` row so the admin can still see it.

  **One exception, added 6 Sep 2026: it is closed on a fully-funded Asset whose meetup has not been
  completed yet.** On a full Asset the seat you vacate goes straight to the waitlist, and leaving
  before anybody has met the team meant handing a stranger your seat over nothing — the churn the
  client asked to stop. The dialog says so and names when it lifts. It lifts the moment the founder
  marks a meetup completed, and then stays available for good — inside the 24-hour refund window and
  after it — so no partner is ever left with no way out. An Asset that is not fully funded is
  unaffected.

Silent partners pay at join and never attend the meeting, but the same 24-hour window applies to
them, since it is opened per-venture when the founder marks a meetup completed. The one exception
is somebody who took a vacated seat from the waitlist (§2b): they joined without a meeting by
design, so their 24 hours are counted from the moment they joined instead.

## 2. Meeting Venue — offline up to 10 partners, online after that

The founder no longer names a Preferred Meeting Venue while creating a listing. That field was
removed: a venue cannot honestly be promised before anybody knows how many partners will turn up,
and past the limit below the Asset can only meet online anyway. The address is now given
**per meetup**, in the schedule dialog, where the group size is actually known.

**The rule the platform enforces:**

- Up to **10 partners**, a meetup may be held **in person** (a physical address) or online — the
  founder chooses.
- The moment an **eleventh partner joins**, or the Asset becomes **100% funded**, in-person meetups
  are closed and every new meetup must be **online**. The Physical option is locked in the schedule
  dialog and the reason is shown. Ten is counted inclusive of the founder, who sits in the room too.
- Meetups **already scheduled** before the Asset crossed that line are left alone — people have
  made travel arrangements — the limit applies to newly scheduled ones.

**The meeting link is provided by the person scheduling the meetup.** Ventures Harbor does not
create Zoom or Google Meet rooms; the founder makes the link in their own account and pastes it into
the schedule dialog, and every partner sees it on their Meetups page. Because online is now the only
option for a large Asset, the link is **required** — an online meetup with no link would be one
nobody could attend.

**Both an in-person and an online meetup open the 24-hour refund window** (§1). The window exists so
a partner can reconsider after meeting the team, and a video call serves that purpose; keeping it to
in-person meetings only would have removed the refund from precisely the largest Assets, which are
the ones that can never meet in person.

Older listings that already stored a venue keep it — nothing was erased, and editing such a listing
does not wipe it.

## 2b. Waitlist — joining a full Asset that still has time left

**The Join Waitlist button is gold.** When an Asset fills up and the Co-Own button turns into Join
Waitlist, it also changes colour — so an investor scanning the marketplace can see which Assets have
filled without reading every button. The "On Waitlist" state, for someone already in the queue, uses
the same gold in a lighter form.

An Asset that has raised its full target but whose listing has not expired is not closed. It is
simply **out of room** — and somebody may still leave. So its **Co-Own** button becomes
**Join Waitlist**, and the Waitlist page in the sidebar is where the rest of it happens.

**Joining the waitlist**

- The button changes only when the Asset is fully funded **and** the listing still has days left.
  An expired, cancelled or sample listing offers no waitlist; neither does one that still has room,
  which can simply be Co-Owned as before.
- Anyone may join except the founder and people already partnered in that Asset. There is **no
  charge** to wait, and no limit on how many people wait.
- Leaving the waitlist is one click and costs nothing.

**When a partner exits**

The moment a partner leaves a full Asset, the seat they vacate is offered to **everybody on the
waitlist at the same time** — a notification and an email, both carrying:

- the **reason they gave for leaving** (or a plain statement that no reason was given),
- the seat's **value** and the **commitment fee** to take it,
- and that **no meeting is required**.

Nobody is contacted ahead of anybody else. The seat is offered as it was vacated: an exiting silent
partner's seat is a silent seat for the same amount, an active partner's an active one.

**Which kind of seat it is, is said twice.** The two are different commitments — a silent seat is
capital, an active seat is work — so the role is named in the notification's **title** as well as its
body ("An Active Partner Seat Has Opened"), which is what the bell dropdown shows before anything is
opened, and again in the email's subject line. The Waitlist page states the rule under *Assets you
are waiting on*: **a seat may be silent or active, and active seats are chosen by the founder.**

**A SILENT seat — first come, first served**

**Claim asks first.** Pressing it opens a confirmation naming the role, the seat's value, the fee
payable now and the length of the hold, and asks *"Do you wish to continue?"* — because claiming is
not browsing: it starts a 10-minute lock that shuts everybody else out of the seat, so a mis-click
both commits the person who made it and stalls the queue behind them.

The first person to confirm **Claim** holds the seat **exclusively for 10 minutes** and pays the
commitment fee within that window. Everyone else is told immediately that it is being claimed. If
the holder does not pay in time — or gives the seat back — it returns to everyone else at once, and
whoever claims next has the same 10 minutes.

**An ACTIVE seat — the founder chooses**

An active partner works in the business, so that seat is **not** first-come. Everyone waiting is
told it is open, and anyone may **apply** for it with the same three things an ordinary
active-partner application carries: a **resume**, a short **message to the founder**, and a
**WhatsApp number**. Applying is free, nothing is charged, and it does **not** hold the seat.

The founder sees every applicant on the Asset's Applications tab — resume, skills, how many of the
listing's required skills they match, their message and a WhatsApp button — and picks one. They have
**up to 7 days** from when the seat opened. The person chosen is notified and has **2 days** to pay
the commitment fee and take the seat.

If the chosen person doesn't pay in time, the seat comes back and the founder can choose again from
the same applicants — nobody is discarded on the way. If none of them are suitable, the founder can
**reopen the seat for new applicants**, which turns down everyone who applied and tells the whole
waitlist the seat is open again. Reopening does not extend the seat's 7-day life.

Nothing about joining the waitlist changed: it is still free, still one click, and you are **not**
asked which kind of partner you want to be. The question is only asked when a seat actually exists,
so nobody uploads a resume for a seat that may never open.

The race is settled at the **claim**, not at the payment, and that is deliberate. Payments go out to
the gateway and come back; if the winner were decided there, several people could pay for one seat
and all but one would have to be refunded by hand. Settling it before any money moves means **nobody
is ever charged for a seat they do not get**.

**Taking the seat**

Whoever takes the seat — the first claimant on a silent seat, the chosen applicant on an active one
— pays the commitment fee and becomes a partner immediately, with **no meeting**. The pledged
investment behind the seat is settled offline with the founder exactly as it is for anyone else.

**Their 24-hour refund window opens the moment they join.** They were admitted deliberately without
a meeting, so the window that normally opens when a meetup is completed (§1) could never open for
them; instead they get the same 24 hours, counted from joining. Every other partner is unaffected.

**While a seat is open, the room is held for the waitlist.** A partner's exit frees capital, and
without this the first passer-by could Co-Own it while the people who had waited for exactly this
were still reading the notification. Anyone trying the ordinary route is told the room belongs to
the waitlist and invited to join it.

Seats not taken within 7 days are withdrawn quietly, as is any seat on a listing that expires or is
cancelled in the meantime.

## 3. Brand / Individual Option

- While creating a venture, the founder selects one of the following:
  - **Brand** – if the founder is creating the venture on behalf of a registered brand/company. The commitment fee is 1%.
  - **Individual** – if the founder is creating the venture in a personal capacity. The commitment fee is 0.5%.

## 4. Active Partner Selection Process

- Active partners do not join immediately after applying.
- They only submit their details until the application deadline.
- After the last date, the founder reviews all applications.
- The founder selects the applicant(s) who best match the venture requirements.
- Only selected applicants receive the join invitation.
- Selected applicants must pay the required commitment fee to confirm their place in the venture.
- Applicants who are not selected are not added as venture partners.

## 5. Partner Types – Sleeping vs Active

- **Sleeping Partner**: invests money and stays at home (no day-to-day involvement). They track how the business is doing through the financial reports.
- **Active Partner**: invests money **plus** takes part in the founder's daily operations.
- If a founder wants an active partner with a specific skill set, the founder can specify the required skill(s) when creating the venture, and only applicants with that skill should be added as active partners.

**A listing may accept silent partners only, both types, or active partners only.** The create form
offers three choices — **Silent Partner**, **Both Types** and **Active Partner**, in that order, with
Both Types selected by default.

Active-only was withdrawn on 2 Sep 2026 and restored on 6 Sep 2026 at the client's request. Nothing
about how active partners work changed in either direction: the application flow, the required
skills and the role staying open after the target is met are the same on an active-only listing as
on a Both Types one. An active-only listing is simply never asked for a silent-partner cap, silent
requirements or a silent equity percentage — it has no silent role for those to describe.

### 5a. Requirements and skills, asked per partner side

One "Partner Requirements" box asked two different questions at once — what the founder wants from
someone bringing money, and what he wants from someone joining the team — so both got answered
vaguely. Each side now has its own box, and the skills field sits under the side it belongs to:

| | Silent Partner | Active Partner |
|---|---|---|
| **Requirements** | optional | optional |
| **Required skills** | **optional** | **required** |

**Why the active side's skills are required.** Every active-partner application is scored against
that list and the founder is shown the result ("2/3 required skills matched"). An empty list quietly
removes the one comparison the founder was given, so a new listing is not allowed to publish without
it. The silent side's are optional and expected to stay blank most of the time: a silent partner is
judged on the capital they bring, not on a skill set.

**Only new listings are held to it.** Editing an existing listing does not demand the skills, so a
founder fixing a typo on a listing published before this rule is never forced to invent one. This is
the same treatment the equity percentages got. **Sample listings are exempt too** — nobody can apply
to one, so there is no applicant to score.

**A listing is only asked about roles it accepts.** On a silent-only listing the whole Active Partner
group is hidden and cleared, so it can never publish requirements for partners it cannot take.

**Existing listings.** A silent-only listing's old requirements text was moved to the silent box —
with no active role, it can only ever have been meant for silent partners. Every other listing keeps
its text as the *active* requirements, which is what the field already was in practice, and starts
with an empty silent box for the founder to fill in when they next edit.

### What happens when a venture reaches 100% funded

Reaching the funding target closes the **money** side of a venture, not the venture itself.
The two partner types therefore close at different moments:

- **Sleeping (silent) partners — closed.** A sleeping partner brings capital and nothing else,
  and once the target is met there is no capital left to bring. The role is greyed out on the
  join page with the reason shown, and the server refuses it even via a direct link.
- **Active partners — still open.** An active partner brings work, which a funded venture still
  needs. The card's button changes from "Join Venture" to **"Apply as Active Partner"**, and the
  normal application flow (résumé → founder reviews → selected → confirm) runs unchanged.

A **fully funded venture that only ever wanted sleeping partners** has nothing left to offer
anyone, so it disappears from Browse and the homepage instead of sitting there at 100% with a
button that cannot work. A fully funded venture that also wanted active partners stays listed,
marked with a green **100% Funded** badge, so people can still apply to join the team.

**No commitment fee is charged to an active partner who joins after the target is met.** The
0.5% is a percentage of the money pledged, and there is no money left to pledge — they are
joining to work, not to fund something already funded. They are a full member in every other
respect: group chat, meetups, financial reports, the venture's member list.

### 5b. Reserving part of the target for active partners (the silent limit)

Until now a venture had a single pot of capital and sleeping partners could fill all of it. A
founder who wanted an operator on the team had no way to keep any money aside for one: by the
time a good active partner applied, the raise could already be closed.

A founder accepting **both** partner types can now set a **Maximum from Silent Partners** when
creating (or editing) the venture. Whatever is left of the target becomes the **active reserve** —
capital only reachable by joining as an active partner.

**Example — a ₹30,00,000 venture, founder putting in nothing, silent limit ₹27,00,000:**

1. Silent partners join and fill up to ₹27,00,000.
2. The moment silent reaches ₹27,00,000 the **silent role locks**, even though the venture is only
   90% funded.
3. The remaining ₹3,00,000 is available **only to active partners**. Someone who wants to put in
   that money must join as an active partner and go through the normal application flow.

**What people see.** A venture with a limit shows two capacity bars on its card, on the homepage
and on the venture page:

```
₹30L Venture Target
[ Silent   ₹27L / ₹27L   FULL ]
[ Active   ₹0   / ₹3L    OPEN ]
```

Once the silent side is full, the card and the venture page say so in words —
**"Silent Partnership Full · Remaining investment: ₹3,00,000 · Only active partners can join this
venture"** — the role badge switches to **Active Only**, and the button goes straight to
**Apply as Active Partner** instead of offering a partner-type choice with a dead option. The
server refuses a silent join past the limit even via a direct link or a hand-made API call, and
tells the visitor the remainder is reserved for active partners rather than claiming the venture
is full.

**Rules that follow from it:**

- **The founder's own contribution is outside both buckets.** The limit is measured against
  `target − founder contribution`, so a founder raising their own stake never silently shrinks the
  silent capacity they advertised.
- **The limit only applies to a "both types" venture.** On a silent-only listing it would fence off
  capital nobody could bring; on an active-only listing there are no silent partners to limit. The
  field is hidden for those choices and cleared if the founder switches to one.
- **A limit equal to (or above) the whole partner pool is the same as no limit** and is stored as
  none, so a later change to the target can't leave a stale restriction behind.
- **An edit cannot set the limit below money silent partners have already committed** — that would
  leave the bucket permanently overdrawn. The save is refused and says so, rather than being
  quietly reduced.
- **Active partners are capped too, by whatever is left over.** If the target is ₹90L and the founder
  caps silent partners at ₹82L, the remaining ₹8L is the active side's — and an active partner cannot
  pledge more than that. Both figures are shown on the role cards, and the join form now offers
  exactly the same maximum the card states.

  Active used to be uncapped, which meant one active partner could take the entire raise and leave
  no room for the silent partners the founder had set aside ₹82L for. The two figures always add up
  to the full partner pool, so the venture can still reach its target exactly — what it cannot do is
  let one side make up for a shortfall on the other.
- **The active side can fill up on its own.** Once the reserve is taken, the venture stops accepting
  active partners and says so, while silent partners can still join — the mirror of what already
  happened when the silent side filled first.
- **A capped-out venture is not a closed one.** It stays on Browse and the homepage, because there
  is still capital to raise and a role to raise it in.
- **Existing listings are unaffected.** A venture created before this feature has no limit, and
  silent partners can still fill its whole target exactly as before.

### Profit distribution frequency

Every founder must state how often profit will be paid out — **Monthly**, **Quarterly**,
**Annually**, or **No regular distribution** — when they create the listing. It used to be
optional ("Not specified"), which is why listings created before this change have no value.

- **It is a required field.** The creation form blocks the step, and the API refuses a
  missing or unrecognised value on both create *and* edit. The edit rule is deliberate: it
  is how an older listing gets a value, since the founder's first save after this change has
  to supply one.
- **Partners can filter Browse by it**, so a listing with no stated schedule is effectively
  invisible to anyone shopping on payout terms. That is the reason it became required.
  The filter keeps a **Not Specified** option so the older listings stay reachable.
- **"No regular distribution" is a valid answer**, not a blank one — it states a policy
  (profit is settled at exit rather than on a schedule) instead of leaving it unstated.
- **It is the founder's stated intent, not something the platform enforces.** Nothing
  schedules a payout or checks that one happened; like the pledged investment itself,
  distribution is settled offline. The monthly report's per-partner share is calculated and
  displayed, never paid.

## 7. Venture Expiry, Cancellation, Listing Extension & Refunds

Every listing now has a real end date (`ventures.listing_ends_at`). Before this, `days_left`
was a number captured once at creation and never decremented, so no listing could ever
actually end — `days_left` is now a derived display value recomputed from the deadline.

**The rules:**

0. **How long a listing runs.** A first listing runs for up to **30 days** — the founder picks
   the number when creating the venture, and the form will not accept more. If it isn't funded
   in time, the one extension is capped at **10 days**: a short second chance, not a second
   full run. Both ceilings are enforced on the server as well as in the form, so an old page or
   a direct API call can't exceed them.

   **The duration is fixed once the venture goes live.** Editing a published listing cannot
   change it: the field is read-only in the edit form and a posted value is ignored by the API.
   A partner weighs how long a listing has left when deciding to join, and a founder who could
   re-open the edit form and top the number back up would have an unlimited listing while
   everyone else is held to 30 days plus one extension. The only window in which it is still
   editable is while the venture is awaiting its listing-fee payment — it has never been
   published and its clock has not started. After that, the **one extension** is the only way
   to add time.
1. A listing that reaches its end date **without being fully funded** moves to status
   `expired`, and the founder chooses one of two things:
   - **Extend the listing** — available **once per venture** (max 10 days), from the founder's dashboard.
   - **Delete the venture** — permanent.
2. If an **already-extended** listing reaches the end of its extended period still unfunded,
   it is **closed automatically** — there is no second decision window.
3. **The founder is reminded every day of that window, by notification *and* by email.** The
   first reminder goes out the moment the listing expires; there is one more on each following
   day, for exactly as many days as the window lasts — two reminders under the default. Each one
   names the real deadline ("closes in 2 days", then "closes in less than 24 hours"), says that
   closing refunds every partner, and links straight to the dashboard where the founder can
   extend or close it. The reminders stop the instant they do either.

   *Email, not just a notification badge:* the whole situation this covers is a founder who has
   stopped opening the site — "kaafi din ho gaye, woh bhool gaya". An in-app notice they never
   log in to see is not a reminder.
4. A founder who ignores every reminder does not freeze the listing indefinitely: after
   `venture_decision_grace_days` (**default 2**, editable in the `settings` table) the platform
   closes it on their behalf. The same number controls both things by design — the founder is
   reminded once a day for exactly as long as they have to decide, so the listing can never be
   closed on a day they were not warned about. *This rule is not in the original brief — it was
   added because without it a listing could sit expired forever while its partners' commitment
   fees stayed unrefundable.*
5. Cancelling — by any of the three routes above — **automatically opens a refund** of the
   commitment fee for every partner who paid one. Nobody has to request it.

**The reminder timeline, end to end** (30-day listing, default 2-day window):

| When | What happens |
|---|---|
| Day 30 | Listing period ends unfunded → status `expired`, stops accepting partners. **Reminder 1** sent. |
| Day 31 | **Reminder 2**, flagged *Final* — "closes in less than 24 hours". |
| Day 32 | No third reminder. Venture **closed automatically**, every partner's commitment fee **refunded automatically**. |

At any point before that last step, extending the listing puts it back to `active` and resets the
reminder count to zero. An already-extended listing that expires again is closed outright (rule 2)
— it gets no second window and therefore no second run of reminders.

**One button, not two.** The founder's dashboard used to carry a **Cancel** button sitting
between Edit and Delete, and nothing on either button explained the difference: Cancel closed
the listing and refunded everyone, while Delete simply refused as soon as a single partner had
joined. There is now a single **Delete Venture** action, and it works whether or not anyone has
joined:

- **Nobody joined and no fee was paid** — the listing is removed completely, along with its
  photos. A reason is optional, because nobody is affected by it.
- **Partners have joined** — a **reason is required**, and it is shown to those partners. Every
  partner's commitment fee is refunded **in full and automatically**, every open application is
  closed, and everyone is notified.

In the second case the listing is closed rather than erased, and stays on the founder's
dashboard marked **DELETED** until the refunds it opened have been paid out. That is deliberate:
the refund queue an admin pays from points at the venture, so deleting the record outright would
leave real payouts with nothing to pay them against. Partners see the venture described as
*cancelled*, which is what it means for them.

If the listing has merely run out of time and still has its one extension available, the same
dialog offers **Extend Listing Instead** — so ending it is never the only visible way out.

**Refunds.** Only the commitment fee is refunded, because it is the only money the platform
ever collects; the pledged principal was always settled offline with the founder. These rows
use `transactions.refund_type = 'venture_cancelled'`, alongside the existing `exit` and
`account_deletion` flows. Unlike those two, no form was filled in, so the bank columns start
NULL — the partner adds them afterwards from their **Payment Statement** page, and the Admin
Panel shows the row as *Awaiting bank details* and refuses to let it be marked paid until then.

**Admin Panel.** A **Closed Ventures** section lists every closed listing with why and when it
closed — *Deleted by founder*, *Closed automatically* (listing period ran out) or *Closed by
admin* — and tracks its refunds: awaiting bank details / ready to pay / paid / rejected. A
matching **Venture Closed** filter appears in Refund Requests. (The stored status is still
`cancelled` and the refund type still `venture_cancelled`; only the wording changed, when the
founder's separate Cancel button was merged into Delete.)

**Who is not refunded:** the founder (never paid a fee), anyone who already left the venture
(they have an exit refund row, or knowingly waived it), and applicants who were never charged —
their open applications are closed as `cancelled` instead.

**Scheduling.** The host has no cron, so expiry is driven by `vh_run_lifecycle_sweep()` in
`config/venture-lifecycle.php`, called from the high-traffic endpoints. It self-throttles to
once every 5 minutes and is idempotent, so it costs one small UPDATE per request.

## 8. Online Payments — PayU Gateway

Until now the "Pay Securely" button did not process a real payment. It simulated one and showed a
success message immediately; the Razorpay config file in the repo was never loaded by anything.
This feature replaces that with a real gateway.

**What is actually charged.** Only the **commitment fee** (0.5% of the pledged investment). The
pledged investment itself is recorded for tracking and settled offline with the founder, exactly as
before — it never goes through the gateway. Founders pay nothing to list a venture.

### How a payment works

PayU is a *redirect* gateway: the partner leaves the site to pay and PayU sends them back. So a
payment happens in three stages rather than one:

1. **Start** — the server checks the partner is actually eligible (venture live, not the founder,
   not already a member, has a phone number), calculates the fee itself, records the attempt, and
   signs the request. Nothing is granted at this point.
2. **Pay** — the partner completes payment on PayU's own page (UPI, cards, net banking, wallets).
3. **Confirm** — PayU sends the result back. The signature is verified and the amount is checked
   against what was recorded in step 1. **Only then** is the membership created, the fee recorded,
   and the venture's raised total updated.

If anything fails or the partner cancels, they get a clear "Payment not completed" page, nothing is
charged, and no membership is created.

### Test Mode and Live Mode

Admin Panel → Settings → Payment Gateway:

- **Disabled** — the old simulated flow. Nothing is charged. Useful for demos.
- **Enabled + Test** — real gateway, test cards only, no real money.
- **Enabled + Live** — real money.

Test and Live are two **separate PayU merchant accounts**, each with its own credentials. Switching
to Live requires the live credentials to be installed on the production server first; if they are
missing, payments are refused rather than falling back to the test account.

**The merchant Salt is never stored in the database or shown in the admin panel.** It is the secret
that signs payment confirmations — anyone holding it could forge a "payment successful" message and
obtain memberships without paying. It lives in a server-side file only.

### What partners see

- Payments appear on their **Payment Statement** exactly as before
- Every payment carries PayU's own reference, so a disputed payment can be traced against the PayU
  dashboard
- Abandoned or failed attempts are recorded separately and never appear as charges

### Deliberate design decisions

- **Failed and abandoned attempts are not treated as transactions.** Most attempts that reach any
  payment gateway are never completed. Recording them as transactions would inflate platform
  earnings, the partner's statement and the refund queue with money that never moved.
- **A duplicated confirmation cannot double-charge or double-join.** Refreshing the return page or
  a repeated message from PayU is safely ignored.
- **A confirmation for the wrong amount is rejected** even if its signature is valid — this blocks
  someone editing the amount before it reaches PayU.
- **In Live mode, a real PayU payment is the only thing that can complete a payment.** The simulated
  "instant join" path stays available in Demo and Test mode, so the platform can still be shown and
  tested without moving money — but in Live mode the server refuses it outright. Previously the
  choice between "go to PayU" and "complete instantly" was made in the browser, so the instant path
  could be triggered by hand and a membership granted without paying. It also meant that a Live site
  whose gateway credentials were missing or wrong quietly made **every** join free, while the admin
  panel still read Live. Now that case refuses the payment and tells the visitor payments are
  temporarily unavailable — nothing is charged, and nobody is signed up for free. The same rule
  applies to a founder's listing fee: in Live mode the venture is not created until PayU confirms.

## 9. Venture Photos & Videos — the listing gallery

Each venture carries up to **25 items**: uploaded photos, uploaded video clips, and YouTube/Vimeo
links. The founder manages them from the venture page ("Manage gallery"); an admin can remove an
item but cannot add or reorder one. The cap was raised from 10 at the client's request — a property
listing needs more room than a business one. Past twelve items the carousel drops its row of dots
and relies on the "n / m" counter in the expand button, which a 25-dot row would otherwise overflow
on a phone.

### Where the gallery appears

The gallery **is** the venture header. The photos and videos fill the top of the venture page and
are swiped through left/right, with the venture logo, title, industry, location, days-left and the
funding progress bar overlaid on top of them.

This replaced an earlier arrangement where the header froze the first photo as a fixed backdrop and
the full gallery sat in a separate box below the tab bar. That showed the same photo twice and left
the rest of the media somewhere a visitor had to scroll to find. One block replaces two.

- **Swipe, arrows, or dots** move between items. Vertical page scrolling is unaffected.
- **The counter/expand button** (bottom right of the header) opens the item full size — the header
  crops photos to fill, so this is how a partner sees the whole image.
- **A venture with no media** keeps the original plain header. Nothing looks broken or empty.
- **The gallery does not change the venture's picture on cards.** Browse, the homepage and every
  saved/dashboard list show the **venture image the founder uploaded** (its profile picture), and
  fall back to the venture's initial only when there isn't one. Uploading gallery photos used to
  replace that picture on every card with whichever gallery photo happened to sort first, so a
  founder's chosen image silently disappeared the moment they added a gallery. If an image file is
  missing, the card shows the initial rather than a broken-image icon.

### Video behaviour

- **On a computer**, an uploaded video clip plays automatically as a moving backdrop, **silently**.
  A speaker button turns the sound on.
- **On a phone or tablet**, video does *not* play automatically — it shows a still frame with a
  play button. Tapping it starts the video with sound. This avoids spending a visitor's mobile data
  on something they did not ask to watch.
- **Only the item on screen ever plays.** Playback stops when the header is scrolled past.
- **YouTube/Vimeo links** always show a thumbnail with a play button and load the player only when
  clicked, so a venture page never pulls a third-party video player before someone wants it.
- Anyone whose device is set to reduce motion gets the paused-with-play-button behaviour everywhere.

### Readability

The header text sits on top of whatever photo is showing, and a listing's photos can be any
brightness. A fixed gradient is drawn over every item, the title carries a shadow, and the funding
figures sit on a frosted panel — so the venture name and the money stay readable on a white photo
just as they do on a dark one.

## 9b. Expected ROI on a Listing

The Investment & Exit block now carries **Expected ROI** directly beside **Equity Distribution** —
what share a partner gets, and what that share is expected to earn, read as one question.

Free text rather than a dropdown, because real answers are ranges with conditions
("18–22% per year from year 2"). Optional like every other field in that block: a founder who
leaves it blank still publishes, and the venture page simply omits the row.

## 9c. Equity & Salary per Role

**Equity Distribution** used to say only *how* ownership is split — Capital-Based, Equal Split or
Negotiated. It never carried the numbers, so a partner could read a whole listing and still not know
what percentage their money buys, or whether anyone draws a salary from the venture.

The founder now fills in a small table on the Capital step:

| Role | Investment | Equity % | Monthly Salary |
|---|---|---|---|
| Founder | Your Contribution | ✅ | ✅ |
| Active Partner | Min. Investment per Member | ✅ | ✅ |
| Silent Partner | Min. Investment per Member | ✅ | — |

**The Investment column is not a new field.** It mirrors the figures already entered above —
*Your Contribution* for the founder, *Min. Investment per Member* for both partner types — and
updates live as those change. Nothing is typed twice, so the table can never state an amount the
venture does not actually use.

**A silent partner has no salary row.** Silent partners are capital-only and take no operational
role in the venture; that is the whole distinction from an active partner. There is no field, and no
column in the database, for one.

Only the roles a venture accepts are shown — a Silent Only listing shows no Active row.

Every box is **optional**: a founder who has not agreed equity yet still publishes, and the venture
page simply leaves the row out rather than printing a table of dashes. A percentage that *is* entered
must be between 0 and 100. A salary of ₹0 is a real answer ("no salary drawn") and is kept as such,
distinct from leaving the box blank.

The table appears in three places, all reading the same figures: the create/edit form, the Review
step before publishing, and the **Investment & Exit Details** block on the venture page, where a
partner sees it before joining.

### 9d. What your money actually buys — the equity calculator

The table quotes every percentage **per member at the minimum investment**, which left the reader to
do the arithmetic for any other amount — and to guess what happens above the minimum. The platform
now works it out for them, live, as they type the figure.

**The rule, stated in full.** An active partner's percentage is what **one minimum investment** buys,
so that minimum is the **active limit for a single partner**. Anything committed above it is not
buying more of the active role — it is extra capital, so it is recognised as **silent (capital-only)
partnership** and earns the silent partner's rate on top.

> A venture with a ₹3,00,000 minimum, quoting **15%** to active partners (7.5% investment + 7.5%
> operations) and **6.7%** to silent partners.
> Committing **₹4,00,000** as an active partner: the first ₹3,00,000 earns the full **15%**, and the
> extra ₹1,00,000 is silent capital earning 6.7% × (1L ÷ 3L) = **2.23%**.
> **Total: 17.23%.**

Two consequences follow from that rule and are worth stating:

- **Operations equity never scales.** It is paid for the work an active partner does, not for
  capital, so it is earned once whatever they bring.
- **Silent partners have no such limit.** Their whole commitment is capital, so it earns the silent
  rate pro rata — ₹6,00,000 at 6.7% per ₹3,00,000 is 13.4%.

A venture that lists **no silent role at all** has nothing for the excess to be recognised as, so
there the investment percentage simply scales with the amount.

**Where it shows.** The figure appears wherever an amount is chosen or confirmed, always from the
same calculation, so no two screens can quote different numbers:

- the **Investment Amount** step of the join flow, under the fee summary, updating as the amount changes;
- the **active-partner application** form, on the same live basis;
- the **Legal Agreement** and **Payment** summaries, so the equity is restated on the screen where
  the partner agrees to it and on the one where they pay;
- the venture page, as a **"What would my investment buy?"** control under the equity table, with the
  rule written out above it in that listing's own figures;
- the create/edit form, so a founder setting the percentages sees the same rule their partners will.

Every figure is indicative and says so — the final split is signed offline with the founder. Where
the founder left a percentage blank, the calculator says the share isn't stated rather than inventing
one, and a commitment that would work out above a 100% stake is shown capped with a warning.

**The founder now has to state the partner percentages.** The Equity & Salary table used to be
optional in full, and almost every listing was published with it empty — which meant the calculator
had nothing to measure against and simply did not appear, on the listings and in the flow where it
mattered most. Publishing a new venture now requires the **investment equity %** for each partner
role the listing accepts: active and silent on a both-types listing, only the relevant one otherwise.
Everything else on the table stays optional — the founder's own figures, both salaries, and
operations equity, which may legitimately be 0 or not yet agreed.

Three things follow deliberately:

- **Editing is not held to the rule.** Listings published before it can still be saved without
  percentages, so a founder is never blocked from fixing a typo by a number they haven't agreed.
- **Existing listings explain the rule instead of hiding it.** Where no percentages were published,
  the join flow and the venture page print the rule in words — that the minimum investment is the
  active limit, and that anything above it is recognised as silent partnership — followed by a plain
  statement that this founder hasn't published their percentages, so the exact share has to be agreed
  with them directly. No figure is invented; the reader learns the rule that will decide their split
  rather than seeing nothing at all.
- **It shows before anything is typed.** On a listing with no percentages the explanation appears as
  soon as the step opens, rather than waiting for an amount that would never produce a number.

## 9d. A Partner's Equity Is Frozen At The Terms They Joined On

A founder can keep editing a live listing, and should be able to. Days run down and nobody has
committed, so they lower the minimum per member; or good partners are arriving, so they take less
of the raise themselves. Both are ordinary, sensible moves.

The problem was that **nothing about a partner's equity was written down.** Their `venture_members`
row held only the amount and the role; every percentage lived on the founder's own listing, and the
share was recomputed from it every time anyone looked. So an edit made for future partners reached
backwards and changed the deal people had already paid for. Concretely: somebody joins with
₹50,000 when the minimum is ₹50,000 and the silent rate is 6%, so they hold 6%. The founder later
drops the minimum to ₹25,000 — and that partner's ₹50,000 is now two tickets, so their share
silently becomes **12%**. Nobody agreed to that, in either direction.

**Each member now carries their own copy of the terms.** It is taken once, at the moment the
membership is paid for, and never rewritten afterwards — a duplicated gateway callback cannot
disturb it. Every route in takes it, because they all pass through the one function that creates a
membership: silent join, active-partner confirmation, the PayU callback, and a claimed waitlist
seat.

- **The partner keeps 6%.** In both directions. This is the point: their share is a record of an
  agreement, not a live reading of somebody else's page. If the founder's edit would have been
  *generous* the answer is still 6% — a number that can move is not a number you agreed to.
- **The next partner buys on the new terms.** Somebody joining after the edit gets ₹25,000 for 6%,
  which is exactly what the founder changed the listing to offer.
- **Partners can now see what they hold.** Before this, the percentage was quoted only while
  deciding whether to join and never shown again afterwards. Their dashboard now states
  *"6% equity — agreed when you joined"* on each Co-Owned Asset, and the Asset page shows it beside
  each partner in the Members list.
- **A changed listing is captioned, never hidden.** Where the founder has edited the listing away
  from what a partner agreed to, the figure carries a lock and a line saying the listing has changed
  and their share has not. The reader is told the two differ rather than being left to notice.

**Two fields lock the moment a first partner joins.**

- **Total Capital Required** — a partner's stake is measured against the size of the raise, so
  moving it after they have paid changes what they bought.
- **Partner Type Accepted** — the shape of the Asset they chose to join. Switching a "Both Types"
  listing to silent-only retires the role an active partner is already filling; switching the other
  way lets operators into an Asset whose partners signed up for capital-only.

Both are shown read-only with the reason. The **minimum per member** and the **founder's own
contribution** deliberately stay editable — that is what a founder with days left actually needs —
and neither can hurt an existing partner now that their equity is frozen. Everything descriptive
(requirements, skills, deadlines, photos, exit terms) stays editable throughout.

**The partner percentages follow the minimum investment.** Every percentage in the equity table is
quoted *per member at the minimum investment*, so the two only mean anything together. Lower the
minimum from ₹50,000 to ₹25,000 and the 6% becomes **3%** — the deal is unchanged, the entry ticket
is simply smaller. Without this, halving the ticket would quietly double the equity the founder
hands over for every rupee raised: the same ₹10,00,000 would cost 240% of the company instead of
120%. It also keeps existing partners consistent with new ones — somebody who put in ₹50,000 under
the old terms holds 6%, and somebody putting in ₹50,000 under the new terms also gets 6%.

**The founder's own equity follows their own money, and they watch it happen.** Halving your
contribution while keeping the same percentage is quietly improving your own deal, so the founder's
*investment* equity rescales with the change: ₹10L at 30% becomes 15% at ₹5L. **This now happens
live in the form** — change Your Contribution and the Investment Equity % moves as you type, with a
brief highlight so it is noticed rather than discovered on save. Their **operations** equity does
not move: it is paid for the work they do, not for capital, the same rule that stops an active
partner's operations equity scaling with their pledge. A founder who types a percentage themselves
is left alone from that point on — the rescale exists to stop a stale number sitting there
unnoticed, not to take the field away.

*Existing partners were back-filled from their listing's terms as they stood on the day this
shipped. Those are the only record that survives — the historical terms were never written down —
and freezing them there is better than letting them keep drifting.*

## 9e. A Funded Asset Closes To Applications, And The Founder's Card Shows What Is Waiting

**Once an Asset is fully funded, the application option disappears — from the card and from the
Asset page both.** It used to stay: the thinking was that an active partner brings work rather than
capital, so the team could keep hiring after the money closed. In practice it left "Apply as Active
Partner" sitting on a finished Asset indefinitely, and every application it produced was one the
founder could only turn down, because there is no seat to give. It is refused on the server too, so
it cannot be reached by any route.

Two states are deliberately **not** affected:

- **Fully funded with listing time still left** still shows **Join Waitlist**. A partner may yet
  exit, and that is what the waitlist is for.
- **Silent partnership full, but the Asset is not funded** still shows **Apply as Active Partner**.
  That Asset has capital left that only an active partner can bring — closing it would hide the one
  route a visitor can still take, and wrongly imply the raise is finished.

**A silent-only Asset has no Applications tab at all.** Applications only exist for active
partners — a silent partner joins and pays in one step, there is nothing to review. On a listing
that accepts silent partners only, the tab is gone rather than sitting there permanently empty.

**The founder's own listing card now shows what is waiting on them.** Previously an application, a
Q&A question, a chat message and a new partner all appeared only in the notification bell, so a
founder with three listings had to open each one to find out which needed attention. Each card now
carries small chips:

- **Amber — waiting on you.** *"2 active partner applications for review"*, *"1 question to
  answer"*. These clear themselves when the founder acts: an application stops counting once it is
  decided, a question once it is answered. Only **active** partner applications appear — a silent
  partner never files one, they join and pay in a single step, so there is nothing to review there.
- **Grey — for context.** *"12 messages"*. The founder's own messages are excluded, so the number
  describes what other people have said.

There is deliberately **no partner headcount** here: the card already states Raised and % Funded, so
a third figure saying the same thing only crowded it.

Every chip is a link straight to the right tab on the Asset page. A listing with nothing happening
shows no chips at all rather than a row of zeroes.

*Note: these count outstanding work, not unread items — the platform does not track what anyone has
read. So "2 applications to review" stays until they are decided, which is the more useful thing for
a founder to see.*

## 9f. An Asset Can Have At Most 25 Members, Including The Founder

A founder never types a partner count — it falls out of the figures they do enter. Whatever the
partners have to raise between them (the target, less the founder's own contribution), divided by
the minimum investment per member, is how many people have to turn up.

So the limit is applied where it actually bites: **as a minimum under the minimum investment.**

> Target ₹1,00,00,000 · your contribution ₹10,00,000 → partners bring ₹90,00,000.
> You count as one of the 25, so there are **24 partner seats** — ₹90,00,000 ÷ 24 =
> **₹3,75,000 each**, and the minimum investment cannot go below that.

The **Est. Members Needed** figure counts you too. Type anything lower and it turns red, with the reason:
*"Maximum Member Limit: 25 (upper limit), to make venture exits and operations easier for members."*
The listing cannot be published until it is fixed. The same rule applies whether the Asset takes
silent partners only or both types — a silent partner is still someone who has to be met, agreed
with and paid out.

**Listings published before this rule are not punished for it.** Some existing Assets already imply
more than 25 partners. Their founders can still edit them freely and can still raise the minimum
investment — they simply cannot lower it further. Nobody is locked out of fixing a typo by a limit
that did not exist when they published.

## 10. Founders Pay a Listing Fee

Listing a venture used to be free. A founder now pays a one-time **0.5% of their own
contribution** — the same flat rate every partner pays on their pledge, and the only money a
founder ever pays this platform.

**Why:** nothing was at stake in creating a listing. A venture could be thrown up in a minute and
abandoned, and partners had no signal separating a founder who meant it from one who didn't.
Putting the founder's own money on the line is that signal.

**What the founder sees.** The fee is shown on the create form, in the same box as the partner
commitment fee, and recalculates as they type their contribution — so it is never a surprise at
the payment step.

**The final button says what it will do.** On the review step the founder sees a highlighted
listing-fee notice — the amount, what percentage of what it is, and that it is not refundable —
and the button itself reads **Pay ₹4,000 & Publish** rather than a bare "Publish Venture". It
tracks the contribution field, so changing that changes the quoted amount. When no gateway is
switched on, or the founder is contributing nothing of their own, the button reverts to **Publish
Venture** — it never quotes a payment that isn't going to be taken.

**When it is charged.**

- **Demo mode (no payment gateway)** — the venture publishes immediately and the fee is recorded
  on the founder's Payment Statement, exactly as a partner's demo payment is.
- **PayU switched on** — the venture is created but held at **Not published yet**: it is
  invisible on Browse, on the homepage and to every other user until the fee clears. The founder
  is sent to PayU, and only PayU's verified reply publishes the listing. It appears on their
  dashboard meanwhile, marked **UNPAID**, with a *Pay listing fee & publish* button — so an
  abandoned or failed payment can simply be retried rather than losing the work.

**The listing period starts when the venture goes live**, not when the form was submitted, so
time spent at the payment gateway doesn't come out of the founder's 30 days.

**The listing fee is not refundable.** If the venture is later deleted or closed, partners get
their commitment fees back in full, but the founder's listing fee is not returned — it bought the
listing. Refunding it would make "create, delete, get your money back" a free round trip and
remove the whole point of charging for a listing. A founder who contributes nothing of their own
pays nothing, since there is no amount to take a percentage of.

---

## 11. Sample Listings — worked examples on an empty site

A brand-new platform has nothing on it. The first founder to arrive lands on an empty Browse page
with a blank form and no idea what a finished listing is supposed to contain — how much detail
belongs in the description, what the exit terms look like filled in, how the numbers read on a
card. **Sample listings** are the answer: a small number of complete, realistic examples published
by the platform itself and pinned to the top of every listing, so the first thing a visitor sees
is what "done" looks like.

**How an admin publishes one.** Admin Panel → **Sample Listings** → *Create Sample Listing*. That
opens **the same four-step wizard a founder uses** — deliberately, because the result has to look
like a real listing rather than an admin-only approximation. Three things differ:

- **A founder name is typed in.** A real listing takes it from the founder's account; a sample
  needs a plausible one, or all the examples read as posted by the same admin.
- **There is no listing duration.** A sample has no deadline (see below).
- **There is no fee.** Publishing a sample costs nothing and takes no payment.

**Up to three at a time.** Three is enough to show variety across industries and enough to be
obviously a set of examples; past that they would start crowding out the real listings they exist
to encourage. The Create button shows the slots remaining and disables itself at the cap; deleting
one frees a slot immediately.

**How a visitor can tell.** Every sample carries a purple **SAMPLE LISTING** badge on its card, on
Browse and on the homepage. Opening it shows a full-width strip across the top of the venture
page — *"This is an example, not a real venture… the figures, the partner terms and the exit
details are illustrative"* — because someone arriving from a direct link or a search result never
sees the card.

**They cannot be joined.** The Join button stays in place, so a visitor can see where the call to
action sits on a real listing, but it reads **Example Only** and does nothing. Anyone who reaches
the join page by a bookmarked or hand-typed link is turned around, and the server refuses the join
outright — nobody is ever charged a commitment fee against a venture that doesn't exist. There is
therefore never a member, a payment or a refund attached to a sample.

**They never expire.** A sample has no listing period, shows *Example listing* where a real card
counts down, and is skipped by the automatic expiry sweep — so it can't quietly disappear a month
after being published. It stays until an admin deletes it.

**They always sort first**, ahead of the company/individual ordering and whatever sort or filter
the visitor has chosen. Filtering to Healthcare or sorting by Most Funded still leads with the
example rather than burying it.

**Removing them.** Delete from the Sample Listings table. Unlike a real venture this is a genuine
deletion, not a closure: there are no partners to refund and no payout history pointing at it.
Once the platform has real ventures of its own, delete all three.

**They stay out of the way of everything else.** Samples don't appear in Venture Moderation (none
of suspend/activate means anything for a listing nobody can join), aren't counted as ventures the
admin participates in, and can't be created by a regular user — a normal account that submits the
sample flag simply gets an ordinary venture, listing fee and all.


## 12. Browse, card and account changes (this release)

**Infinite scroll replaces pagination.** Browse loads every matching venture in one request and
renders it 12 cards at a time as you scroll; there are no page 1/2/3 links. A "Load more" button
appears for browsers without `IntersectionObserver` and as the keyboard path.

**Four cards per row on desktop.** The filter sidebar was narrowed and its three longest radio
groups became dropdowns to pay for the width; the grid steps down 4 → 3 → 2 → 1 as the window
narrows.

**Expected ROI is now two numbers, and required.** A founder enters a range ("14 to 18% per year")
plus optional conditions. Partners can filter Browse by ROI band, and the range shows on every card
beside Days Left. Bands match by overlap, so a 14–18% venture appears under both "10–20%" and, if it
straddled, the next band up. Required on create *and* edit, so older listings gain a value the first
time their founder saves.

**Profit distribution says "Annually"** rather than "Yearly" — wording only.

**Sample listings can be walked through.** Clicking Join on a sample now walks the real steps with a
"preview only" banner and a locked payment step, instead of turning the visitor away at the door.
Nothing is submitted and no payment can be taken — the platform still refuses a sample join and a
sample payment outright. Samples also show the silent/active capacity bars, drawn from illustrative
figures an admin enters, since a sample has no real partners. The roles a sample lists are
selectable during the walkthrough — an active-only sample offers Active Partner and marks Silent
as Not Accepted, exactly as the real Asset would — while the platform still refuses the join and
the payment behind it.

**The sample marker is green**, not gold. The gold cursor/hover glow on cards is unchanged.

**"Active Only" is amber like "Silent Only".** Amber now means "one partner role only"; blue means
both roles are open.

**Money shows in crore.** ₹1,00,00,000 reads as ₹1Cr rather than ₹100.0L, everywhere.

**Deleting a venture always leaves a record.** Previously a venture nobody had joined was erased
outright and never appeared in the admin's Closed Ventures list. Now every closure is recorded there
— with 0 refunds when nobody had joined — while still disappearing from the founder's own dashboard.

**The agreement step shows the venture's real exit terms.** It used to state "60-day notice period"
on every listing regardless of what the founder set.

**A founder cannot delete their account while a venture is still running.** Walking away mid-listing
would leave partners committed to a venture nobody can close, decide applications on, or answer for.
The Delete Account dialog now checks first and, if anything is outstanding, explains why instead of
letting someone type DELETE and fill in their bank details only to be refused. Two things hold it:

- **A venture that isn't closed** — including one still awaiting payment, one past its deadline
  waiting on the founder's decision, and a suspended one. Each is listed by name with its status and
  member count, and the founder is pointed at their dashboard to close them.
- **A refund one of their ventures owes** that our team hasn't paid out yet. Closing a venture opens
  a refund for every paying partner; until those are settled there is real money outstanding against
  that person's ventures.

Once every venture is closed and every refund settled, deletion proceeds as normal. Being a *partner*
in someone else's venture never blocks it — that can be exited separately. Nor does a refund the
person is themselves owed, including the one the deletion form files on the way out.

**Deleting an account is permanent, and the email becomes free to use again.** Deletion has always
been a *soft* delete — nothing is erased, because refunds, transactions and any venture the person
founded all point at their record. But the email stayed spent with it: signing up again answered "an
account with this email already exists" and logging in answered "this account has been deleted", so
there was no way back onto the platform at all.

The address is now released to the next signup, which gets a **brand-new, empty account** — no
ventures, no partnerships, no payment history. It does not bring the old account back. The
confirmation dialog says this before anything happens: *"This cannot be undone… signing up again with
the same email creates a brand-new, empty account"*, with **No, keep my account** / **Yes, delete
permanently** as the two answers, on top of the existing type-DELETE box. It also now warns that any
venture the person listed stays live and unmanageable, and that a refund has to be claimed on the way
out rather than later.

**Only an admin can undo a deletion.** The user directory marks deleted accounts and offers
**Restore**, which lets the person sign in again with everything they had. If somebody has already
taken their email in the meantime, the panel says so and asks for a different address — two accounts
cannot share one. The directory and the refund queue always show a deleted user's real address, so
there is still somewhere to send a pending payout.

**Individual vs Company accounts.** Chosen at signup. An individual profile shows personal details
(name, photo, age, occupation, skills, experience); a company profile shows company details (name,
logo, description, website, registration number, founded year, size). The choice also sets the
founder type on every venture that account lists — it is no longer asked per venture. Accounts
created before this change default to Individual and are asked to confirm on their next profile
save.

## 13. Q&A — a conversation, not a suggestion box

The Q&A tab on a venture reads like a comment thread. Three roles, and the page never blurs them:

- **Anyone signed in can ask.** A question is public, because the next person considering the
  venture usually has the same one.
- **Only the founder can answer.** The answer sits directly under the question, tinted, with a
  **FOUNDER** badge on it. Nobody else is offered an answer box, and the server refuses the attempt
  even if the request is hand-made — an answer carries the founder's authority, so it has to be
  theirs. Until they reply, the question shows *Awaiting the founder's reply*.
- **Anyone signed in can reply**, under a question or under the founder's answer — including the
  founder, who joins the discussion under their own answer like anyone else.

**Replies are one level deep.** Replying to a reply puts an **@name** at the front of the box and
adds it to the same list, exactly as Instagram does. A thread can therefore never march sideways
off the edge of the column, however long the argument runs. Replies start collapsed behind
**View replies (n)** so one busy question cannot bury the next.

**One heart, and nothing beside it.** Questions, answers and replies can each be liked. The heart
sits on the right of the row with its count directly underneath, and it is a toggle: **hollow** means
you haven't liked it, **solid red** means you have, and tapping again takes it back. Signed-out
visitors see every tally but are asked to sign in when they tap.

**Disliking is gone.** It was removed at the client's request, and removed properly — there is no
button, no endpoint, and no column in the database that could hold one. Likes recorded before the
change are kept; the old dislikes are simply no longer counted anywhere.

**Removing things.** You can delete your own question or your own reply; the founder and an admin
can delete anything on their venture. Deleting a question takes its replies and every like on the
thread with it, so nothing is left pointing at a conversation that no longer exists.

Maps to `config/migrate.sql` step 48; covered end to end by `test_qna_thread.php`.

---

## 14. From "ventures" to a marketplace for real-world assets

The platform's language moved from a business-venture-only model to a **fractional-ownership
marketplace for real-world assets**. Same product, wider frame: a listing is an *asset* somebody
can co-own, and the site says so everywhere a visitor reads it.

### What changed in words

The homepage headline is now **"Co-Own. Build. Scale. — Partial Ownership in High-Yield Assets."**
("Trade" was dropped deliberately: nothing on this platform trades. There is no secondary market,
and ownership transfer is frequently "Not allowed" on a listing's own exit terms, so the word
promised something the product does not do.) The hero's primary button alternates every two
seconds between **Explore Marketplace** and **Start Co-Investing**, the second in gold — held on
the first label for anyone who has asked their system for reduced motion.

Browse became **Explore Marketplace**, its results read *Showing N Active Assets*, and the
dashboard now speaks of **My Portfolio**, **Co-Owned Assets** and **Saved Assets** in place of the
venture wording.

### Which word appears depends on whose screen it is

The rename is not total, and that is the point. After reviewing the finished sweep the client kept
"Asset" for everyone looking at somebody else's listing, and brought **"Venture" back for the
founder looking at their own**.

A person **creating or holding** a listing sees *Venture*: the button is **List Your Venture**
everywhere it appears, the create form asks for a **Venture Title**, the dashboard counts
**My Ventures** and lists **My Listed Ventures**, the meetup dialog says **Select Venture**, a
founder's own card in the gallery reads **Your Venture**, and every notification and email sent to
a founder speaks of *your Venture*.

A person **browsing or co-owning** sees *Asset*: Browse and the Marketplace, the filter pills and
**Asset Class**, **Co-Owned Assets**, **Saved Assets**, **Capital Invested**, the **Co-Own**
button, and anything addressed to a partner rather than a founder.

The distinction is one of audience, not of two different things: a Venture and an Asset are the
same listing, named for whoever is reading. It follows the way people actually speak — you *build a
venture* of your own, and you *co-own an asset* of someone else's.

The header's **List Your Venture** button is blue rather than gold, so the one action that starts a
listing is not competing with the gold accents used for highlights elsewhere.

**Only what a person reads changed.** URLs, database tables and columns, API actions, CSS class
names and JS variables still say `venture`. That is deliberate: the rename is reversible, breaks
no existing link or bookmark, and needed no migration of live data.

### What changed in behaviour: asset classes

The marketplace filter pills are **All Assets · Real Estate · Businesses · IP & Royalties ·
Franchise · Infrastructure**, and they filter on a real field.

They could not have used `category`. That column is *derived* from `industry` by a fixed map and
only ever holds a handful of slugs, defaulting everything unmapped to `franchise` — a "Real Estate"
pill filtering on it would have matched almost nothing, and a "Businesses" pill nothing at all.
`industry` was the wrong home too: it is a 24-value list founders pick from, and collapsing it
would have taken choices away from every listing already published.

So **`ventures.asset_class`** is its own, deliberately coarse axis alongside both. A founder picks
it on the create form, pre-filled from the industry they chose so nobody is asked the same question
twice. The sidebar **Industry** filter keeps its full list and its meaning; the pills answer a
different question ("what kind of thing is this?"), which is what stops the two controls
contradicting each other.

Existing listings were back-filled from what they already said about themselves — Real Estate and
Infrastructure read off the industry, everything else filed as **Businesses**, since a business is
the only thing the platform accepted before asset classes existed. `franchise` is deliberately
*not* back-filled from `category = 'franchise'`: that slug is the map's fallback, so trusting it
would have filed most of the site under Franchise.

The homepage shows only the classes that actually have a listing behind them. That grid is a
preview of a few assets rather than the whole catalogue, and a pill that empties it would look
broken; new classes appear there on their own as listings arrive. Browse, which searches
everything, always shows all six.

### The minimum commitment is a floor, not a suggestion

The venture page's "What would my investment buy?" calculator used to answer any number. On a
listing with a ₹50,000 minimum it told a visitor that ₹40,000 buys 4% — a share they could never
actually purchase, because the join flow rejects the amount a step later. It now names the minimum
instead of quoting a percentage, on both the venture page and inside the join flow, because both
render through the same one calculator.

Maps to `config/migrate.sql` step 49; covered by `test_asset_class.php` (create, update, filter)
and section 5 of `test_equity_calc.js` (the floor).

### One Asset, several classes

A listing is not always one thing. Land bought to be developed is Real Estate *and* Infrastructure,
and the marketplace should show it under both. A founder may now pick **up to three** Asset Classes,
and the listing appears under every pill it claims.

They may also **name a class of their own** — "Vintage Cars", say — when none of the five fits. A
class named this way shows on the Asset page and can be found by search, but it does not become one
of the marketplace filter buttons: those stay the canonical five, because a filter row that grows
with whatever each founder invents stops being a way to navigate. The Asset page marks a
founder-named class differently from a marketplace one, so a visitor can tell which is which.

Three is a deliberate ceiling. Without one, a listing could tick every class and appear everywhere,
which would make the filters worth nothing to the people using them.

Maps to `config/migrate.sql` step 50; covered by section 5 of `test_asset_class.php`.
