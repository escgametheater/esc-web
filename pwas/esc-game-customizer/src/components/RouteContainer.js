import React, { PureComponent } from 'react';
import { withTheme } from 'emotion-theming';

class RouteContainer extends PureComponent {
  render() {
    const { theme } = this.props;
    return (
      <div style={{ 
        backgroundColor: theme.backgroundColor,
        padding: "1rem 2rem",
      }}>
        {this.props.children}
      </div>
    );
  }
}

export default withTheme(RouteContainer);
