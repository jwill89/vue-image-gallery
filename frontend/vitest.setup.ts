// Pin the timezone so any date-formatting assertions are deterministic
// regardless of the machine running the suite.
process.env.TZ = 'UTC'
