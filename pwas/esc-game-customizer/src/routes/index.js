import React, { PureComponent } from "react";
import { Route, Switch } from "react-router-dom";
import Branding from "./Branding";
import Content from "./Content";

export default class Routes extends PureComponent {
  render() {
    const passedProps = this.props;
    
    return (
      <Switch>
        <Route path="/branding" render={ props => <Branding {...props} {...passedProps} /> } />
        <Route path="/content" render={ props => <Content {...props} {...passedProps} /> } />
        <Route render={ props => <Branding {...props} {...passedProps} /> } />
      </Switch>
    )
  }
}
