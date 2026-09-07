# Google Play review access

The production review accounts are disabled by default. Configure all variables in Railway before enabling them:

```dotenv
GOOGLE_PLAY_REVIEW_ACCESS=true
GOOGLE_PLAY_REVIEW_PIN=<six-digit reusable PIN>
GOOGLE_PLAY_REVIEW_SELLER_EMAIL=review.seller@nofiodobigode.app.br
GOOGLE_PLAY_REVIEW_SELLER_PASSWORD=<permanent password with at least 10 characters>
GOOGLE_PLAY_REVIEW_BUYER_EMAIL=review.buyer@nofiodobigode.app.br
GOOGLE_PLAY_REVIEW_BUYER_PASSWORD=<permanent password with at least 10 characters>
```

On deployment, `GooglePlayReviewSeeder` creates or refreshes both verified users and assigns the active Ouro plan for ten years. Only these configured email addresses accept the reusable PIN for login 2FA and electronic signatures. All other users retain random, five-minute email codes.

Use dedicated passwords that are not shared with any personal or administrative account. Enter the credentials and reusable PIN directly in Play Console, never in source control.
